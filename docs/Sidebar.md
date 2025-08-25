# Documentation Sidebar

This file serves as the navigation structure for the Laravel OneClickLogin documentation. Copy this structure to your GitHub Wiki sidebar.

## 📚 Documentation Navigation

### Getting Started
- [🏠 Home](Home)
- [⚡ Quick Start](Quick-Start)
- [📦 Installation](Installation)
- [⚙️ Configuration](Configuration)

### Usage Guide
- [🔗 Link Creation Options](Link-Creation-Options)
- [🎯 API Reference](API-Reference)
- [⌨️ Console Commands](Console-Commands)

### Examples
- [🛒 E-commerce Platform](Examples-E-commerce)
- [📱 Single Page Application](Examples-SPA)
- [🏢 Multi-Tenant Application](Examples-Multi-Tenant)

### Advanced Topics
- [🔒 Security Considerations](Security)
- [📊 Performance Optimization](Performance)
- [🔄 Migration Guide](Migration)
- [🎨 Customization](Customization)

### Support
- [🔧 Troubleshooting](Troubleshooting)
- [❓ FAQ](FAQ)
- [🐛 Known Issues](Known-Issues)
- [📝 Changelog](Changelog)

---

### 📖 Quick Reference

#### Essential Commands
```bash
# Install package
composer require grazulex/laravel-oneclicklogin

# Publish configuration
php artisan vendor:publish --provider="Grazulex\OneClickLogin\ShareLinkServiceProvider"

# Run migrations
php artisan migrate

# Generate magic link
php artisan magic-link:generate user@example.com

# Cleanup expired links
php artisan magic-link:cleanup
```

#### Basic Usage
```php
use Grazulex\OneClickLogin\Facades\OneClickLogin;

// Generate magic link
$link = OneClickLogin::for('user@example.com')
    ->redirectTo('/dashboard')
    ->expiresInMinutes(30)
    ->generate();

// Consume magic link
$consumer = OneClickLogin::consume($token);
if ($consumer->isValid() && $consumer->canUse()) {
    $link = $consumer->consume();
    // Authenticate user...
}
```

#### Configuration Highlights
```php
// config/sharelink.php
return [
    'default_expiration_minutes' => 15,
    'table_name' => 'magic_links',
    'cleanup' => ['enabled' => true],
    'rate_limiting' => ['enabled' => true],
];
```

---

### 🚀 Popular Features

- **🎯 Zero-configuration setup** - Works out of the box
- **⏰ Flexible expiration** - Minutes, hours, or days
- **🔒 Security-first** - Rate limiting, signed URLs, token hashing
- **📊 Context data** - Rich metadata support
- **🧹 Auto-cleanup** - Automated expired link removal
- **📱 Multi-platform** - Web, API, SPA support
- **🏢 Multi-tenant** - Organization-aware magic links
- **⚡ High performance** - Optimized database queries

---

### 🛠️ Development Tools

#### Artisan Commands
- `magic-link:generate` - Create new magic link
- `magic-link:cleanup` - Remove expired links
- `magic-link:stats` - View usage statistics
- `magic-link:verify` - Check link validity
- `magic-link:revoke` - Disable active links
- `magic-link:extend` - Extend expiration time

#### Events
- `MagicLinkGenerated` - When link is created
- `MagicLinkConsumed` - When link is used
- `MagicLinkExpired` - When expired link accessed

#### Testing Helpers
```php
// Test link generation
$link = OneClickLogin::for('test@example.com')->generate();
$this->assertNotNull($link->getUrl());

// Test link consumption
$consumer = OneClickLogin::consume($link->token);
$this->assertTrue($consumer->isValid());
```

---

### 📞 Support & Community

- **📚 Documentation**: Complete guides and examples
- **🐛 Bug Reports**: GitHub Issues
- **💡 Feature Requests**: GitHub Discussions
- **📧 Email Support**: package@example.com
- **💬 Community Chat**: Discord/Slack

---

### 📄 License & Contributing

- **License**: MIT License
- **Contributing**: Welcome! See CONTRIBUTING.md
- **Code of Conduct**: See CODE_OF_CONDUCT.md
- **Security**: See SECURITY.md

---

*Last updated: August 2025 | Version 1.0*
