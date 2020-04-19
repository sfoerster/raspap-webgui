#!/bin/bash

set -e

WIFI_IFACE=$(ls /sys/class/ieee80211/*/device/net/ | awk 'NR==1{print $1}')

if [ -z "$WIFI_IFACE" ]; then
    echo "No wifi interface found. Exiting."
    exit 0;
fi

#RASPAP_VOL=/opt/mistborn_volumes/extra/raspap/etc-raspap
#TMP_DIR=/tmp/mistborn-raspap
RASPAP_VOL=/etc/raspap
TMP_DIR=/var/www/html

if [ "$DISTRO" == "ubuntu" ]; then
    sudo apt-get install -y software-properties-common
    sudo add-apt-repository -y ppa:ondrej/php
fi

# install on gateway
sudo apt-get install -y hostapd vnstat dnsmasq

# front-end
sudo apt-get install -y lighttpd iptables-persistent qrencode php7.3-cgi

# lighttpd
sudo lighttpd-enable-mod fastcgi-php || true
sudo service lighttpd force-reload
sudo systemctl restart lighttpd.service

# stop conflicting dns
sudo systemctl stop systemd-resolved || true
sudo systemctl disable systemd-resolved || true

# restart dnsmasq
sudo systemctl restart dnsmasq

# install dhcp server on Ubuntu, Debian, etc. (just not Raspbian)
if [ ! "$DISTRO" == "raspbian" ]; then
    sudo apt-get install -y dhcpcd5
fi

sudo mkdir -p $RASPAP_VOL 
sudo mkdir -p $RASPAP_VOL/backups
sudo mkdir -p $RASPAP_VOL/networking
sudo mkdir -p $RASPAP_VOL/hostapd
sudo mkdir -p $RASPAP_VOL/lighttpd

sudo cat /etc/dhcpcd.conf | sudo tee -a $RASPAP_VOL/networking/defaults > /dev/null

# copy files from raspap repo
sudo rm -rf $TMP_DIR
sudo git clone https://github.com/sfoerster/raspap-webgui.git -b $(git symbolic-ref --short HEAD || echo "master") $TMP_DIR

# sudoers
sudo cp $TMP_DIR/installers/raspap.sudoers /etc/sudoers.d/090_raspap

# raspap
sudo cp $TMP_DIR/raspap.php $RASPAP_VOL
sudo sed -i "s/wlan0/$WIFI_IFACE/g" $RASPAP_VOL/raspap.php

# permissions
sudo chown -R www-data:www-data $TMP_DIR
sudo chown -R www-data:www-data /etc/raspap

sudo mv $TMP_DIR/installers/*log.sh $RASPAP_VOL/hostapd
sudo mv $TMP_DIR/installers/service*.sh $RASPAP_VOL/hostapd
sudo cp $TMP_DIR/installers/configport.sh $RASPAP_VOL/lighttpd

# permissions
sudo chown -c root:www-data /etc/raspap/hostapd/*.sh
sudo chmod 750 /etc/raspap/hostapd/*.sh
sudo chown -c root:www-data /etc/raspap/lighttpd/*.sh

### System Service ###
sudo mv $TMP_DIR/installers/raspapd.service /lib/systemd/system
sudo systemctl daemon-reload
sudo systemctl enable raspapd.service

sudo mv /etc/default/hostapd ~/default_hostapd.old || true
sudo cp /etc/hostapd/hostapd.conf ~/hostapd.conf.old || true

sudo cp $TMP_DIR/config/default_hostapd /etc/default/hostapd
sudo cp $TMP_DIR/config/hostapd.conf /etc/hostapd/hostapd.conf
sudo sed -i "s/wlan0/$WIFI_IFACE/g" /etc/hostapd/hostapd.conf
sudo cp $TMP_DIR/config/dnsmasq.conf /etc/dnsmasq.d/090_raspap.conf
sudo sed -i "s/wlan0/$WIFI_IFACE/g" /etc/dnsmasq.d/090_raspap.conf
sudo cp $TMP_DIR/config/dhcpcd.conf /etc/dhcpcd.conf
sudo sed -i "s/wlan0/$WIFI_IFACE/g" /etc/dhcpcd.conf
sudo cp config/config.php $TMP_DIR/includes/
sudo sed -i "s/wlan0/$WIFI_IFACE/g" $TMP_DIR/includes/config.php

# systemd-networkd
sudo systemctl stop systemd-networkd || true
sudo systemctl disable systemd-networkd || true
sudo cp $TMP_DIR/config/raspap-bridge-br0.netdev /etc/systemd/network/raspap-bridge-br0.netdev
sudo cp $TMP_DIR/config/raspap-br0-member-eth0.network /etc/systemd/network/raspap-br0-member-eth0.network 

# enable packet forwarding
echo "net.ipv4.ip_forward=1" | sudo tee /etc/sysctl.d/90_raspap.conf > /dev/null
sudo sysctl -p /etc/sysctl.d/90_raspap.conf
sudo /etc/init.d/procps restart

# iptables
sudo iptables -t nat -D POSTROUTING -j MASQUERADE || true
sudo iptables -t nat -D POSTROUTING -s 192.168.50.0/24 ! -d 192.168.50.0/24 -j MASQUERADE || true
sudo iptables -t nat -A POSTROUTING -j MASQUERADE
sudo iptables -t nat -A POSTROUTING -s 192.168.50.0/24 ! -d 192.168.50.0/24 -j MASQUERADE
sudo iptables-save | sudo tee /etc/iptables/rules.v4

# hostapd
sudo systemctl unmask hostapd.service
sudo systemctl enable hostapd.service
