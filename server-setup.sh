#!/bin/bash
# server-setup.sh - Complete WordPress server setup with Caddy, MariaDB, PHP 8.4
# Run once on fresh Ubuntu 24.04 LTS server
# Usage: ./server-setup.sh

set -euo pipefail
trap 'echo "ERROR: Setup failed at line $LINENO" >&2; exit 1' ERR

# Configuration
TIMEZONE="America/Chicago"
SITES_DIR="/var/www"
BACKUP_DIR="/backups/wordpress"
LOG_FILE="/var/log/server-setup.log"
SWAP_SIZE="4G"
SWAP_FILE="/swapfile"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo "$(date -Is) $*" | tee -a "$LOG_FILE"
}

log_colored() {
    echo -e "${2:-$GREEN}$(date -Is) $1${NC}" | tee -a "$LOG_FILE"
}

require_root() {
    [[ $EUID -eq 0 ]] || { 
        echo -e "${RED}This script must be run as root${NC}" >&2
        exit 1
    }
}

# Check if running on Ubuntu 24.04
check_ubuntu() {
    if ! grep -q "Ubuntu 24.04" /etc/os-release; then
        echo -e "${RED}This script requires Ubuntu 24.04 LTS${NC}" >&2
        exit 1
    fi
}

# Create necessary directories
setup_directories() {
    log_colored "Creating directory structure"
    mkdir -p "$SITES_DIR" "$BACKUP_DIR" "$(dirname "$LOG_FILE")"
    chmod 755 "$SITES_DIR"
    chmod 750 "$BACKUP_DIR"
}

# Update system and install base packages
update_system() {
    log_colored "Updating system packages"
    export DEBIAN_FRONTEND=noninteractive
    
    apt update -y
    apt upgrade -y
    
    # Install essential packages
    apt install -y \
        curl \
        wget \
        unzip \
        git \
        ufw \
        fail2ban \
        htop \
        nano \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release \
        unattended-upgrades
}

# Configure timezone and NTP
setup_timezone() {
    log_colored "Setting timezone to $TIMEZONE"
    timedatectl set-timezone "$TIMEZONE"
    timedatectl set-ntp true
}

# Create swap file if not exists
setup_swap() {
    if [[ ! -f "$SWAP_FILE" ]]; then
        log_colored "Creating ${SWAP_SIZE} swap file"
        fallocate -l "$SWAP_SIZE" "$SWAP_FILE"
        chmod 600 "$SWAP_FILE"
        mkswap "$SWAP_FILE"
        swapon "$SWAP_FILE"
        echo "$SWAP_FILE none swap sw 0 0" >> /etc/fstab
        
        # Optimize swappiness for VPS
        echo "vm.swappiness=10" >> /etc/sysctl.conf
        echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf
        sysctl -p
    else
        log_colored "Swap file already exists"
    fi
}

# Install Caddy web server
install_caddy() {
    log_colored "Installing Caddy web server"
    
    # Add Caddy repository
    apt install -y debian-keyring debian-archive-keyring apt-transport-https
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
    
    apt update -y
    apt install -y caddy
    
    # Create basic Caddyfile
    cat > /etc/caddy/Caddyfile << 'EOF'
{
    email wordpress@ruralimpactgroup.com
    admin localhost:2019
}

# Default response for undefined domains
:80 {
    respond "Server ready" 200
}
EOF
    
    # Enable and start Caddy
    systemctl enable caddy
    systemctl start caddy
    
    log_colored "Caddy installed and started"
}

# Install MariaDB
install_mariadb() {
    log_colored "Installing MariaDB"
    
    apt install -y mariadb-server mariadb-client
    systemctl enable mariadb
    systemctl start mariadb
    
    log_colored "MariaDB installed" "$YELLOW"
    echo -e "${YELLOW}You must run 'mysql_secure_installation' after this script completes${NC}"
}

# Install PHP 8.4
install_php() {
    log_colored "Installing PHP 8.4"
    
    # Add Ondrej PHP repository for PHP 8.4
    add-apt-repository -y ppa:ondrej/php
    apt update -y
    
    # Install PHP 8.4 and extensions
    apt install -y \
        php8.4-fpm \
        php8.4-mysql \
        php8.4-curl \
        php8.4-gd \
        php8.4-intl \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-xmlrpc \
        php8.4-soap \
        php8.4-zip \
        php8.4-cli \
        php8.4-opcache \
        php8.4-readline \
        php8.4-common \
        php8.4-bcmath
    
    # Configure PHP-FPM for WordPress
    cat > /etc/php/8.4/fpm/pool.d/wordpress.conf << 'EOF'
[wordpress]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 1000

; WordPress specific settings
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 128M
php_admin_value[post_max_size] = 128M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_vars] = 3000
EOF
    
    # Optimize PHP.ini for WordPress
    PHP_INI="/etc/php/8.4/fpm/php.ini"
    sed -i 's/;opcache.enable=1/opcache.enable=1/' "$PHP_INI"
    sed -i 's/;opcache.memory_consumption=128/opcache.memory_consumption=256/' "$PHP_INI"
    sed -i 's/;opcache.max_accelerated_files=10000/opcache.max_accelerated_files=10000/' "$PHP_INI"
    sed -i 's/;opcache.validate_timestamps=1/opcache.validate_timestamps=0/' "$PHP_INI"
    sed -i 's/;opcache.revalidate_freq=2/opcache.revalidate_freq=0/' "$PHP_INI"
    
    # Enable and start PHP-FPM
    systemctl enable php8.4-fpm
    systemctl start php8.4-fpm
    
    log_colored "PHP 8.4 installed and configured"
}

# Install WP-CLI
install_wp_cli() {
    log_colored "Installing WP-CLI"
    
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
    
    # Verify installation
    if wp --info --allow-root >/dev/null 2>&1; then
        log_colored "WP-CLI installed successfully"
    else
        log_colored "WP-CLI installation failed" "$RED"
        exit 1
    fi
}

# Configure UFW firewall
setup_firewall() {
    log_colored "Configuring UFW firewall"
    
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing
    
    # Allow SSH, HTTP, HTTPS
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    ufw --force enable
    
    log_colored "UFW firewall configured"
}

# Configure Fail2ban
setup_fail2ban() {
    log_colored "Configuring Fail2ban"
    
    # Create local jail configuration
    cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
backend = systemd

[sshd]
enabled = true
port = ssh
logpath = %(sshd_log)s
maxretry = 3
bantime = 7200
EOF
    
    systemctl enable fail2ban
    systemctl start fail2ban
    
    log_colored "Fail2ban configured"
}

# Configure automatic security updates
setup_auto_updates() {
    log_colored "Configuring automatic security updates"
    
    # Configure unattended-upgrades
    cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
    
    cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};

Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";
EOF
    
    systemctl enable unattended-upgrades
    systemctl start unattended-upgrades
    
    log_colored "Automatic security updates configured"
}

# Harden SSH
harden_ssh() {
    log_colored "Hardening SSH configuration"
    
    # Backup original config
    cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup
    
    # Apply security settings
    sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
    sed -i 's/#PermitRootLogin yes/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
    sed -i 's/X11Forwarding yes/X11Forwarding no/' /etc/ssh/sshd_config
    
    # Add additional security settings
    cat >> /etc/ssh/sshd_config << 'EOF'

# Additional security settings
ClientAliveInterval 300
ClientAliveCountMax 2
MaxAuthTries 3
MaxSessions 2
Protocol 2
EOF
    
    # Test SSH config and restart
    sshd -t
    systemctl restart sshd
    
    log_colored "SSH hardened" "$YELLOW"
    echo -e "${YELLOW}WARNING: Password authentication is now disabled. Ensure you have SSH keys configured!${NC}"
}

# Set up daily backup script
setup_backup_script() {
    log_colored "Setting up backup automation"
    
    cat > /usr/local/bin/backup-wordpress << 'EOF'
#!/bin/bash
# Daily WordPress backup script

BACKUP_DIR="/backups/wordpress"
SITES_DIR="/var/www"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Backup each WordPress site
for site_dir in "$SITES_DIR"/*; do
    if [[ -d "$site_dir" && -f "$site_dir/wp-config.php" ]]; then
        site_name=$(basename "$site_dir")
        
        echo "Backing up $site_name..."
        
        # Backup database
        wp db export "$BACKUP_DIR/${site_name}_db_${DATE}.sql" --path="$site_dir" --allow-root
        gzip "$BACKUP_DIR/${site_name}_db_${DATE}.sql"
        
        # Backup files
        tar -czf "$BACKUP_DIR/${site_name}_files_${DATE}.tar.gz" -C "$SITES_DIR" "$site_name"
        
        # Clean old backups
        find "$BACKUP_DIR" -name "${site_name}_*" -mtime +$RETENTION_DAYS -delete
    fi
done

echo "Backup completed: $(date)"
EOF
    
    chmod +x /usr/local/bin/backup-wordpress
    
    # Add cron job for daily backups at 2 AM
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-wordpress >> /var/log/backup.log 2>&1") | crontab -
    
    log_colored "Backup automation configured"
}

# Final system optimizations
optimize_system() {
    log_colored "Applying system optimizations"
    
    # Kernel parameters for web server
    cat >> /etc/sysctl.conf << 'EOF'

# WordPress server optimizations
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 65536 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
net.ipv4.tcp_congestion_control = bbr
net.core.default_qdisc = fq
EOF
    
    sysctl -p
    
    log_colored "System optimizations applied"
}

# Print final status and next steps
print_summary() {
    log_colored "Server setup completed successfully!" "$GREEN"
    
    echo -e "\n${GREEN}=== SERVER SETUP COMPLETE ===${NC}"
    echo -e "${GREEN}Caddy:${NC} Installed and running"
    echo -e "${GREEN}MariaDB:${NC} Installed (run mysql_secure_installation)"
    echo -e "${GREEN}PHP 8.4:${NC} Installed with FPM"
    echo -e "${GREEN}WP-CLI:${NC} Ready for WordPress installations"
    echo -e "${GREEN}Firewall:${NC} UFW enabled (ports 22, 80, 443)"
    echo -e "${GREEN}Security:${NC} Fail2ban active, SSH hardened"
    echo -e "${GREEN}Backups:${NC} Daily backups configured"
    
    echo -e "\n${YELLOW}NEXT STEPS:${NC}"
    echo -e "1. Run: ${GREEN}mysql_secure_installation${NC}"
    echo -e "2. Install WordPress management scripts"
    echo -e "3. Create your first site: ${GREEN}wp-install domain.com${NC}"
    
    echo -e "\n${YELLOW}IMPORTANT FILES:${NC}"
    echo -e "Caddy config: /etc/caddy/Caddyfile"
    echo -e "Sites directory: $SITES_DIR"
    echo -e "Backups directory: $BACKUP_DIR"
    echo -e "Setup log: $LOG_FILE"
}

# Main execution
main() {
    log_colored "Starting WordPress server setup" "$GREEN"
    
    require_root
    check_ubuntu
    setup_directories
    update_system
    setup_timezone
    setup_swap
    install_caddy
    install_mariadb
    install_php
    install_wp_cli
    setup_firewall
    setup_fail2ban
    setup_auto_updates
    harden_ssh
    setup_backup_script
    optimize_system
    print_summary
    
    log_colored "All tasks completed successfully!" "$GREEN"
}

# Run main function
main "$@"