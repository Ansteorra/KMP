# Remove any existing Go installation
sudo apt-get remove -y golang-go

# Detect architecture and download appropriate Go version
cd ~
ARCH=$(uname -m)
if [ "$ARCH" = "x86_64" ]; then
    # AMD64 architecture
    GO_PACKAGE="go1.22.0.linux-amd64.tar.gz"
elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then
    # ARM64 architecture
    GO_PACKAGE="go1.22.0.linux-arm64.tar.gz"
else
    echo "Unsupported architecture: $ARCH"
    exit 1
fi

echo "Detected architecture: $ARCH, downloading $GO_PACKAGE"
wget "https://go.dev/dl/$GO_PACKAGE"
sudo rm -rf /usr/local/go
sudo tar -C /usr/local -xzf "$GO_PACKAGE"
rm "$GO_PACKAGE"

# Update PATH to include Go
export PATH=$PATH:/usr/local/go/bin
echo 'export PATH=$PATH:/usr/local/go/bin' >> ~/.bashrc

# Verify Go installation
go version

# Now install mermerd with the updated Go version
go install github.com/KarnerTh/mermerd@latest

sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)



# create systemd service file
sudo rm /etc/init.d/mailpit
sudo cp $(echo $REPO_PATH)/.devcontainer/init_env/mailpit.init.d /etc/init.d/mailpit
sudo chmod +x /etc/init.d/mailpit
sudo update-rc.d mailpit defaults

#make default config for mailpit
sudo rm /etc/default/mailpit
sudo touch /etc/default/mailpit
sudo rm -rf  $(echo $REPO_PATH)/app/webroot/bootstrap_u_l
sudo sh -c "echo \"export MP_SMTP_AUTH='$MP_SMTP_AUTH'\" >> /etc/default/mailpit"
sudo sh -c "echo \"export MP_SMTP_AUTH_ALLOW_INSECURE='$MP_SMTP_AUTH_ALLOW_INSECURE'\" >> /etc/default/mailpit"

# enable and start the service
sudo service mailpit start

wget -O phpunit.phar https://phar.phpunit.de/phpunit-10.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit

sudo npm install
sudo npx playwright install-deps

sudo apt-get install -y default-jdk
export JAVA_HOME=$(readlink -f /usr/bin/java | sed "s:/bin/java::")
sudo echo "JAVA_HOME=$JAVA_HOME" >> /etc/environment
echo "export JAVA_HOME=$JAVA_HOME" >> ~/.bashrc