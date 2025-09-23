#!/bin/bash
# wp-install - Complete WordPress site installation with Caddy configuration
# Usage: wp-install domain.com [site_title] [admin_user] [admin_email]

set -euo pipefail
trap 'echo "ERROR: WordPress installation failed at line $LINENO" >&2; exit 1' ERR

# Configuration
SITES_DIR="/var/www"
CADDY_CONFIG="/etc/caddy/Caddyfile"
PHP_SOCK="/run/php/php8.4-fpm.sock"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Usage information
usage() {
    echo "Usage: wp-install domain.com [site_title] [admin_user] [admin_email]"
    echo ""
    echo "Examples:"
    echo "  wp-install example.com"
    echo "  wp-install example.com 'My WordPress Site'"
    echo "  wp-install example.com 'My Site' admin admin@example.com"
    echo ""
    echo "The script will prompt for any missing required information."
    exit 1
}

log_colored() {
    echo -e "${2:-$GREEN}$(date -Is) $1${NC}"
}

# Validate domain format
validate_domain() {
    local domain="$1"
    if [[ ! "$domain" =~ ^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$ ]]; then
        echo -e "${RED}Invalid domain format: $domain${NC}" >&2
        exit 1
    fi
}

# Check if domain already exists
check_existing_site() {
    local domain="$1"
    if [[ -d "$SITES_DIR/$domain" ]]; then
        echo -e "${RED}Site already exists: $SITES_DIR/$domain${NC}" >&2
        echo "Remove the existing directory or choose a different domain."
        exit 1
    fi
}

# Generate secure password
generate_password() {
    openssl rand -base64 32 | tr -d "=+/" | cut -c1-25
}

# Create database and user
create_database() {
    local domain="$1"
    local db_name db_user db_pass
    
    # Clean domain for database naming (replace dots and hyphens with underscores)
    db_name="wp_$(echo "$domain" | sed 's/[.-]/_/g')"
    db_user="$db_name"
    db_pass=$(generate_password)
    
    log_colored "Creating database: $db_name"
    
    # Create database and user
    mysql -u root -p <<EOF
CREATE DATABASE \`$db_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER \`$db_user\`@'localhost' IDENTIFIED BY '$db_pass';
GRANT ALL PRIVILEGES ON \`$db_name\`.* TO \`$db_user\`@'localhost';
FLUSH PRIVILEGES;
EOF
    
    # Return values via global variables
    DB_NAME="$db_name"
    DB_USER="$db_user"
    DB_PASS="$db_pass"
}

# Download and configure WordPress
install_wordpress() {
    local domain="$1" title="$2" admin_user="$3" admin_email="$4"
    local site_path="$SITES_DIR/$domain"
    local admin_pass
    
    admin_pass=$(generate_password)
    
    log_colored "Installing WordPress for $domain"
    
    # Create site directory
    mkdir -p "$site_path"
    
    # Download WordPress
    wp core download --path="$site_path" --allow-root
    
    # Create wp-config.php with security enhancements
    wp config create \
        --path="$site_path" \
        --dbname="$DB_NAME" \
        --dbuser="$DB_USER" \
        --dbpass="$DB_PASS" \
        --dbhost=localhost \
        --dbcharset=utf8mb4 \
        --dbcollate=utf8mb4_unicode_ci \
        --allow-root \
        --extra-php <<PHP
// Security hardening
define('DISALLOW_FILE_EDIT', true);
define('WP_AUTO_UPDATE_CORE', 'minor');
define('AUTOMATIC_UPDATER_DISABLED', false);

// Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Disable comments and pingbacks globally
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);

// Performance optimizations
define('WP_CACHE', true);
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Database optimizations
define('WP_POST_REVISIONS', 3);
define('AUTOSAVE_INTERVAL', 300);
define('WP_CRON_LOCK_TIMEOUT', 120);
define('EMPTY_TRASH_DAYS', 30);

// Performance optimizations
define('WP_CACHE', true);
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Database optimizations
define('WP_POST_REVISIONS', 5);
define('AUTOSAVE_INTERVAL', 300);
define('WP_CRON_LOCK_TIMEOUT', 120);
define('EMPTY_TRASH_DAYS', 30);

// Security keys (auto-generated)
define('AUTH_KEY',         '$(openssl rand -base64 64)');
define('SECURE_AUTH_KEY',  '$(openssl rand -base64 64)');
define('LOGGED_IN_KEY',    '$(openssl rand -base64 64)');
define('NONCE_KEY',        '$(openssl rand -base64 64)');
define('AUTH_SALT',        '$(openssl rand -base64 64)');
define('SECURE_AUTH_SALT', '$(openssl rand -base64 64)');
define('LOGGED_IN_SALT',   '$(openssl rand -base64 64)');
define('NONCE_SALT',       '$(openssl rand -base64 64)');
PHP
    
    # Install WordPress
    wp core install \
        --path="$site_path" \
        --url="https://$domain" \
        --title="$title" \
        --admin_user="$admin_user" \
        --admin_password="$admin_pass" \
        --admin_email="$admin_email" \
        --allow-root
    
    # Store admin password for output
    ADMIN_PASS="$admin_pass"
}

# Install and configure essential plugins
install_plugins() {
    local domain="$1"
    local site_path="$SITES_DIR/$domain"
    
    log_colored "Installing essential plugins"
    
    # Remove default plugins
    wp plugin delete hello akismet --path="$site_path" --allow-root 2>/dev/null || true
    
    # Install essential plugins
    wp plugin install --path="$site_path" --activate --allow-root \
        classic-editor \
        fluent-form \
        fluent-smtp \
        seo-by-rank-math \
        ninjafirewall \
        independent-analytics
    
    # Configure basic WordPress settings
    wp option update default_ping_status closed --path="$site_path" --allow-root
    wp option update default_comment_status closed --path="$site_path" --allow-root
    wp option update blog_public 0 --path="$site_path" --allow-root  # Discourage search engines initially
    
    # Disable comments globally
    wp option update default_pingback_flag 0 --path="$site_path" --allow-root
    wp option update default_ping_status closed --path="$site_path" --allow-root
    wp option update comment_registration 1 --path="$site_path" --allow-root
    wp option update comment_moderation 1 --path="$site_path" --allow-root
    wp option update comments_notify 0 --path="$site_path" --allow-root
    wp option update moderation_notify 0 --path="$site_path" --allow-root
    
    # Close comments on existing posts/pages
    wp post list --post_type=post --format=ids --path="$site_path" --allow-root | xargs -r wp post update --comment_status=closed --path="$site_path" --allow-root
    wp post list --post_type=page --format=ids --path="$site_path" --allow-root | xargs -r wp post update --comment_status=closed --path="$site_path" --allow-root
    
    # Clean up default content
    wp post delete 1 --force --path="$site_path" --allow-root 2>/dev/null || true  # Delete "Hello World" post
    wp post delete 2 --force --path="$site_path" --allow-root 2>/dev/null || true  # Delete sample page
    wp comment delete 1 --force --path="$site_path" --allow-root 2>/dev/null || true  # Delete sample comment
    
    # Create standard pages in draft status
    wp post create --post_type=page --post_title="About" --post_status=draft --path="$site_path" --allow-root
    wp post create --post_type=page --post_title="Contact" --post_status=draft --path="$site_path" --allow-root
    
    # Set proper timezone
    wp option update timezone_string 'America/Chicago' --path="$site_path" --allow-root
    
    # Update permalinks to SEO-friendly structure
    wp rewrite structure '/%postname%/' --path="$site_path" --allow-root
    wp rewrite flush --path="$site_path" --allow-root
}

# Configure Caddy for the new site
configure_caddy() {
    local domain="$1"
    local site_path="$SITES_DIR/$domain"
    
    log_colored "Configuring Caddy for $domain"
    
    # Check if this is the first site (update global email)
    if ! grep -q "email.*@.*\." "$CADDY_CONFIG"; then
        log_colored "Updating Caddy global email configuration" "$YELLOW"
        # This will be updated with a real email when user provides admin_email
    fi
    
    # Add site configuration to Caddyfile
    cat >> "$CADDY_CONFIG" << EOF

# WordPress site: $domain
$domain, www.$domain {
    root * $site_path
    encode gzip zstd
    
    # Handle WordPress permalinks
    try_files {path} {path}/ /index.php?{query}
    
    # PHP processing
    php_fastcgi unix:$PHP_SOCK
    
    # Security headers
    header {
        # Security
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
        
        # Hide server info
        -Server
        -X-Powered-By
    }
    
    # WordPress security
    @wp-admin {
        path /wp-admin/*
        not path /wp-admin/admin-ajax.php
    }
    handle @wp-admin {
        # Add rate limiting for wp-admin in production
    }
    
    # Block access to sensitive files and XML-RPC
    @sensitive {
        path *.log *.sql .htaccess .htpasswd wp-config.php readme.html readme.txt license.txt
        path /wp-content/uploads/*.php
        path /xmlrpc.php
    }
    handle @sensitive {
        respond 404
    }
    
    # Optimize static files
    @static {
        file
        path *.css *.js *.ico *.png *.jpg *.jpeg *.gif *.svg *.woff *.woff2 *.ttf *.eot
    }
    handle @static {
        header Cache-Control "public, max-age=31536000, immutable"
    }
    
    # Logging
    log {
        output file /var/log/caddy/$domain.log
        format json
    }
}
EOF
    
    # Test Caddy configuration
    if caddy validate --config "$CADDY_CONFIG" >/dev/null 2>&1; then
        log_colored "Caddy configuration valid"
    else
        log_colored "Caddy configuration invalid" "$RED"
        echo "Configuration error in $CADDY_CONFIG"
        exit 1
    fi
    
    # Reload Caddy
    systemctl reload caddy
    log_colored "Caddy reloaded with new site configuration"
}

# Set proper file permissions
set_permissions() {
    local domain="$1"
    local site_path="$SITES_DIR/$domain"
    
    log_colored "Setting file permissions"
    
    # Set ownership
    chown -R www-data:www-data "$site_path"
    
    # Set directory permissions
    find "$site_path" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$site_path" -type f -exec chmod 644 {} \;
    
    # Secure wp-config.php
    chmod 600 "$site_path/wp-config.php"
    
    # Make wp-content writable for uploads and updates
    chmod 775 "$site_path/wp-content"
    find "$site_path/wp-content" -type d -exec chmod 775 {} \;
}

# Update Caddy global email if this is first site
update_caddy_email() {
    local admin_email="$1"
    
    if [[ "$admin_email" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        # Update global email in Caddyfile if it's still the default
        if grep -q "email admin@localhost" "$CADDY_CONFIG"; then
            sed -i "s/email admin@localhost/email $admin_email/" "$CADDY_CONFIG"
            log_colored "Updated Caddy global email to $admin_email" "$YELLOW"
        fi
    fi
}

# Display final information
print_summary() {
    local domain="$1" title="$2" admin_user="$3" admin_email="$4"
    
    echo ""
    log_colored "WordPress installation completed successfully!" "$GREEN"
    
    echo -e "\n${GREEN}=== SITE INFORMATION ===${NC}"
    echo -e "${BLUE}Domain:${NC} https://$domain"
    echo -e "${BLUE}Title:${NC} $title"
    echo -e "${BLUE}WordPress Admin:${NC} https://$domain/wp-admin/"
    echo -e "${BLUE}Admin User:${NC} $admin_user"
    echo -e "${BLUE}Admin Password:${NC} $ADMIN_PASS"
    echo -e "${BLUE}Admin Email:${NC} $admin_email"
    
    echo -e "\n${GREEN}=== DATABASE INFORMATION ===${NC}"
    echo -e "${BLUE}Database Name:${NC} $DB_NAME"
    echo -e "${BLUE}Database User:${NC} $DB_USER"
    echo -e "${BLUE}Database Password:${NC} [Stored in wp-config.php]"
    
    echo -e "\n${GREEN}=== INSTALLED PLUGINS ===${NC}"
    echo -e "${BLUE}•${NC} Classic Editor"
    echo -e "${BLUE}•${NC} Fluent Forms"
    echo -e "${BLUE}•${NC} Fluent SMTP"
    echo -e "${BLUE}•${NC} RankMath SEO"
    echo -e "${BLUE}•${NC} Ninja Firewall"
    echo -e "${BLUE}•${NC} Independent Analytics"
    
    echo -e "\n${YELLOW}=== NEXT STEPS ===${NC}"
    echo -e "1. Point your domain DNS to this server's IP address"
    echo -e "2. Visit https://$domain to verify the site loads"
    echo -e "3. Log into WordPress admin to complete plugin configuration"
    echo -e "4. Configure Fluent SMTP for email delivery"
    echo -e "5. Run RankMath setup wizard for SEO optimization"
    echo -e "6. Configure Ninja Firewall security settings"
    
    echo -e "\n${YELLOW}=== IMPORTANT ===${NC}"
    echo -e "${RED}Save the admin password in your password manager!${NC}"
    echo -e "Site files: $SITES_DIR/$domain"
    echo -e "Site logs: /var/log/caddy/$domain.log"
}

# Main function
main() {
    local domain="${1:-}"
    local title="${2:-}"
    local admin_user="${3:-admin}"
    local admin_email="${4:-}"
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        echo -e "${RED}This script must be run as root${NC}" >&2
        exit 1
    fi
    
    # Check if domain provided
    if [[ -z "$domain" ]]; then
        usage
    fi
    
    # Validate and check domain
    validate_domain "$domain"
    check_existing_site "$domain"
    
    # Prompt for missing information
    if [[ -z "$title" ]]; then
        read -p "Site title: " title
        [[ -z "$title" ]] && title="WordPress Site"
    fi
    
    if [[ -z "$admin_email" ]]; then
        read -p "Admin email: " admin_email
        while [[ ! "$admin_email" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; do
            echo -e "${RED}Please enter a valid email address${NC}"
            read -p "Admin email: " admin_email
        done
    fi
    
    log_colored "Starting WordPress installation for $domain" "$GREEN"
    
    # Execute installation steps
    create_database "$domain"
    install_wordpress "$domain" "$title" "$admin_user" "$admin_email"
    install_plugins "$domain"
    set_permissions "$domain"
    configure_caddy "$domain"
    update_caddy_email "$admin_email"
    
    # Display summary
    print_summary "$domain" "$title" "$admin_user" "$admin_email"
    
    log_colored "Installation completed successfully!" "$GREEN"
}

# Execute main function with all arguments
main "$@"