#!/bin/bash

UNAME=$(uname)
if [ "$UNAME" == "Linux" ] ; then
	currentPath=$PWD
	pkgs=(epel-release wget unzip curl git)
	
	if [ -f /etc/redhat-release ]; then
		sudo yum update -y

		for pkg in "${pkgs[@]}"; do
			if ! type $pkg >/dev/null ; then
				sudo yum install $pkg -y
			fi
		done

		if ! type php >/dev/null ; then
			sudo rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm
			sudo yum -y --enablerepo=remi-php74 install php 
			sudo yum -y --enablerepo=remi-php74 install php-{xml,soap,xmlrpc,mbstring,json,gd,mcrypt,zip,xsl,curl}
		fi

		if ! type go >/dev/null ; then
			sudo yum install golang -y
		fi
	fi

	if [ -f /etc/lsb-release ]; then
		sudo apt-get update -y

		for pkg in "${pkgs[@]}"; do
			if ! type $pkg >/dev/null ; then
				sudo apt-get install $pkg -y
			fi
		done

		if ! type php >/dev/null ; then
			sudo apt -y install software-properties-common
			sudo add-apt-repository ppa:ondrej/php
			sudo apt-get update
			sudo apt-get -y install php7.4
			sudo apt-get install -y php7.4-{bcmath,bz2,intl,gd,mbstring,mysql,zip,common,xml,soap,xmlrpc,json,xsl,curl}
		fi

		if ! type go >/dev/null ; then
			sudo apt-get install golang -y
		fi
	fi
	
	if ! type composer >/dev/null ; then
		php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

		HASH="$(wget -q -O - https://composer.github.io/installer.sig)"
		php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

		php composer-setup.php --install-dir=/usr/local/bin --filename=composer
	fi

elif [[ "$UNAME" == CYGWIN* || "$UNAME" == MINGW* ]] ; then
	echo "Windows"
fi

export COMPOSER_ALLOW_SUPERUSER=1; composer show;
composer update

cd $currentPath/package/hakrawler && go run main.go -v
cd $currentPath/package/httpx && go run main.go -version
cd $currentPath/package/subfinder && go run main.go -v

cd $currentPath

echo -e "\e[92mFinished Install\e[0m\n"
echo -e "\e[33mGo: \e[0m"
go version
echo -e "\e[33mPHP: \e[0m"
php -v
