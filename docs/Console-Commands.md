# Console Commands

Laravel OneClickLogin provides several Artisan commands to help you manage magic links in your application.

## Table of Contents

- [magic-link:cleanup](#magic-linkcleanup)
- [magic-link:prune](#magic-linkprune)
- [magic-link:stats](#magic-linkstats)
- [magic-link:generate](#magic-linkgenerate)
- [magic-link:verify](#magic-linkverify)
- [magic-link:revoke](#magic-linkrevoke)
- [magic-link:extend](#magic-linkextend)

## magic-link:cleanup

Removes expired magic links from the database to keep your table clean and optimize performance.

### Usage

```bash
php artisan magic-link:cleanup
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--force` | Skip confirmation prompt | false |
| `--older-than=DAYS` | Only remove links older than specified days | 0 |
| `--dry-run` | Show what would be deleted without actually deleting | false |
| `--batch-size=SIZE` | Number of records to delete per batch | 1000 |

### Examples

```bash
# Remove all expired links (with confirmation)
php artisan magic-link:cleanup

# Remove without confirmation
php artisan magic-link:cleanup --force

# Remove links expired for more than 7 days
php artisan magic-link:cleanup --older-than=7

# See what would be deleted without actually deleting
php artisan magic-link:cleanup --dry-run

# Process in smaller batches
php artisan magic-link:cleanup --batch-size=500
```

### Output Example

```
Magic Link Cleanup
==================

Found 1,234 expired magic links.
- Expired more than 7 days ago: 987
- Recently expired (last 7 days): 247

 Do you want to continue? (yes/no) [no]:
 > yes

Deleting expired magic links...
 1234/1234 [████████████████████████████] 100%

✅ Successfully deleted 1,234 expired magic links.
```

---

## magic-link:prune

Alias for the cleanup command. Provides the same functionality with a shorter name.

### Usage

```bash
php artisan magic-link:prune
```

### Options

Same as `magic-link:cleanup`.

---

## magic-link:stats

Displays comprehensive statistics about magic links in your database.

### Usage

```bash
php artisan magic-link:stats
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--period=DAYS` | Period for recent activity stats | 7 |
| `--detailed` | Show detailed breakdown by date | false |
| `--export=FORMAT` | Export stats to file (json, csv) | none |

### Examples

```bash
# Show basic statistics
php artisan magic-link:stats

# Show stats for last 30 days
php artisan magic-link:stats --period=30

# Show detailed daily breakdown
php artisan magic-link:stats --detailed

# Export stats to JSON file
php artisan magic-link:stats --export=json
```

### Output Example

```
Magic Link Statistics
====================

📊 Overall Statistics
Total Links Created: 15,432
Active Links: 234
Expired Links: 12,987
Consumed Links: 12,345
Never Consumed: 3,087

📈 Success Metrics
Success Rate: 80.0% (12,345 consumed / 15,432 total)
Average Time to Consumption: 4.2 minutes
Most Active Hour: 09:00-10:00 (1,234 links)
Most Active Day: Monday (2,456 links)

⏱️ Recent Activity (Last 7 days)
Generated: 456
Consumed: 398
Expired: 58
Success Rate: 87.3%

📧 Top Email Domains
@gmail.com: 4,567 (29.6%)
@company.com: 3,210 (20.8%)
@outlook.com: 2,156 (14.0%)
@yahoo.com: 1,890 (12.2%)
Others: 3,609 (23.4%)

🔒 Security Events
Failed Consumption Attempts: 156
Expired Link Attempts: 89
IP Mismatches: 12
User Agent Mismatches: 3
```

---

## magic-link:generate

Creates a new magic link from the command line. Useful for testing or administrative purposes.

### Usage

```bash
php artisan magic-link:generate {email}
```

### Arguments

| Argument | Description | Required |
|----------|-------------|----------|
| `email` | Email address for the magic link | Yes |

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--redirect=URL` | Redirect URL after consumption | null |
| `--expires-in=MINUTES` | Expiration time in minutes | 15 |
| `--context=JSON` | Context data as JSON string | {} |
| `--copy` | Copy the URL to clipboard | false |
| `--qr` | Generate QR code for the link | false |
| `--send-email` | Send the link via email | false |

### Examples

```bash
# Generate basic magic link
php artisan magic-link:generate user@example.com

# Generate with custom redirect and expiration
php artisan magic-link:generate user@example.com --redirect=/dashboard --expires-in=60

# Generate with context data
php artisan magic-link:generate user@example.com --context='{"source":"admin","priority":"high"}'

# Generate and copy to clipboard
php artisan magic-link:generate user@example.com --copy

# Generate QR code
php artisan magic-link:generate user@example.com --qr

# Generate and send via email
php artisan magic-link:generate user@example.com --send-email
```

### Output Example

```
✅ Magic Link Generated Successfully

📧 Email: user@example.com
🔗 URL: https://app.example.com/magic-link/abc123def456...
⏰ Expires: 2025-08-25 10:30:00 (in 15 minutes)
📍 Redirect: /dashboard
📋 Context: {"source":"admin","priority":"high"}

🔗 Full URL:
https://app.example.com/magic-link/abc123def456ghi789jkl012mno345pqr678stu901vwx234yz

✅ URL copied to clipboard!
```

---

## magic-link:verify

Verifies the validity of a magic link token without consuming it.

### Usage

```bash
php artisan magic-link:verify {token}
```

### Arguments

| Argument | Description | Required |
|----------|-------------|----------|
| `token` | Magic link token to verify | Yes |

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--detailed` | Show detailed information | false |
| `--check-security` | Verify IP and user agent constraints | false |

### Examples

```bash
# Basic verification
php artisan magic-link:verify abc123def456...

# Detailed verification
php artisan magic-link:verify abc123def456... --detailed

# Check security constraints
php artisan magic-link:verify abc123def456... --check-security
```

### Output Example

```
Magic Link Verification
======================

✅ Token Status: VALID
📧 Email: user@example.com
⏰ Expires: 2025-08-25 10:30:00 (in 12 minutes)
🔄 Status: Not consumed
📍 Redirect: /dashboard

📋 Context Data:
{
    "source": "admin",
    "priority": "high",
    "user_id": 123
}

🔒 Security Information:
- Created from IP: 192.168.1.100
- User Agent: Mozilla/5.0...
- Same IP required: No
- Same User Agent required: No

✅ This link can be consumed.
```

---

## magic-link:revoke

Revokes (disables) one or more magic links, preventing them from being consumed.

### Usage

```bash
php artisan magic-link:revoke {token?}
```

### Arguments

| Argument | Description | Required |
|----------|-------------|----------|
| `token` | Specific token to revoke (optional) | No |

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--email=EMAIL` | Revoke all links for specific email | null |
| `--all-expired` | Revoke all expired links | false |
| `--all-active` | Revoke all active links | false |
| `--reason=TEXT` | Reason for revocation | null |
| `--force` | Skip confirmation for bulk operations | false |

### Examples

```bash
# Revoke specific token
php artisan magic-link:revoke abc123def456...

# Revoke all links for an email
php artisan magic-link:revoke --email=user@example.com

# Revoke all expired links
php artisan magic-link:revoke --all-expired --force

# Revoke with reason
php artisan magic-link:revoke abc123def456... --reason="Security breach"
```

### Output Example

```
Magic Link Revocation
====================

🔍 Found magic link:
📧 Email: user@example.com
⏰ Expires: 2025-08-25 10:30:00
🔄 Status: Active

 Are you sure you want to revoke this magic link? (yes/no) [no]:
 > yes

✅ Magic link revoked successfully.
📝 Reason: Security breach
```

---

## magic-link:extend

Extends the expiration time of existing magic links.

### Usage

```bash
php artisan magic-link:extend {token}
```

### Arguments

| Argument | Description | Required |
|----------|-------------|----------|
| `token` | Magic link token to extend | Yes |

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--minutes=MINUTES` | Extend by specified minutes | null |
| `--hours=HOURS` | Extend by specified hours | null |
| `--days=DAYS` | Extend by specified days | null |
| `--until=DATETIME` | Extend until specific date/time | null |
| `--reason=TEXT` | Reason for extension | null |

### Examples

```bash
# Extend by 30 minutes
php artisan magic-link:extend abc123def456... --minutes=30

# Extend by 2 hours
php artisan magic-link:extend abc123def456... --hours=2

# Extend until specific date
php artisan magic-link:extend abc123def456... --until="2025-08-25 18:00:00"

# Extend with reason
php artisan magic-link:extend abc123def456... --hours=1 --reason="User request"
```

### Output Example

```
Magic Link Extension
===================

🔍 Current Status:
📧 Email: user@example.com
⏰ Current Expiration: 2025-08-25 10:30:00
🔄 Status: Active

⏰ New Expiration: 2025-08-25 11:30:00 (+1 hour)
📝 Reason: User request

 Confirm extension? (yes/no) [yes]:
 > yes

✅ Magic link extended successfully.
```

---

## Automation and Scheduling

### Automatic Cleanup

You can schedule automatic cleanup in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up expired links daily
    $schedule->command('magic-link:cleanup --force --older-than=7')
             ->daily()
             ->at('03:00');
    
    // Generate weekly stats
    $schedule->command('magic-link:stats --export=json')
             ->weekly()
             ->sundays()
             ->at('23:00');
}
```

### Monitoring Commands

Create monitoring commands for production:

```php
// app/Console/Commands/MonitorMagicLinks.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Grazulex\OneClickLogin\Models\MagicLink;

class MonitorMagicLinks extends Command
{
    protected $signature = 'magic-link:monitor {--alert-threshold=1000}';
    
    protected $description = 'Monitor magic link usage and send alerts';

    public function handle()
    {
        $threshold = $this->option('alert-threshold');
        $activeCount = MagicLink::notExpired()->count();
        
        if ($activeCount > $threshold) {
            $this->error("⚠️ High number of active magic links: {$activeCount}");
            // Send alert to monitoring system
        } else {
            $this->info("✅ Magic link count normal: {$activeCount}");
        }
        
        // Check for suspicious activity
        $recentFailures = MagicLink::whereDate('created_at', today())
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '>', 'expires_at')
            ->count();
            
        if ($recentFailures > 10) {
            $this->warn("⚠️ High number of expired link attempts: {$recentFailures}");
        }
    }
}
```

### Bulk Operations

```bash
# Clean up all expired links older than 30 days
php artisan magic-link:cleanup --older-than=30 --force

# Revoke all active links for maintenance
php artisan magic-link:revoke --all-active --reason="Maintenance window"

# Generate links for a list of users
cat users.txt | xargs -I {} php artisan magic-link:generate {} --send-email
```

These commands provide comprehensive management capabilities for magic links in your Laravel application, from basic maintenance to advanced monitoring and bulk operations.
