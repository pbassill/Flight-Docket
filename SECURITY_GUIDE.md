# Security Quick Reference Guide

## For Developers

### CSRF Protection
Every form MUST include CSRF token:
```php
// In controller/page
$csrfToken = \OTR\Security::generateCsrfToken();

// In HTML form
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

// In processing script
if (!\OTR\Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('CSRF validation failed.');
}
```

### Input Validation
Always validate user inputs:
```php
use OTR\Security;

// ICAO codes (4 uppercase letters)
if (!Security::validateIcaoCode($code)) {
    // Handle error
}

// Aircraft registration
if (!Security::validateRegistration($reg)) {
    // Handle error
}

// Sanitize filenames
$safe = Security::sanitizeFilename($userInput);
```

### Security Headers
Add to every public page:
```php
\OTR\Security::setSecurityHeaders();
```

### Session Security
Start session securely:
```php
\OTR\Security::startSecureSession();
```

### Error Handling
Initialize error handler:
```php
\OTR\ErrorHandler::register($logPath);
```

### File Uploads
Validate all uploads:
```php
if (!\OTR\Uploads::isPdfUpload($file, $config['uploads'])) {
    // Reject upload
}
```

## Security Checklist for New Features

- [ ] All user inputs validated and sanitized
- [ ] Output encoded (htmlspecialchars, urlencode, etc.)
- [ ] CSRF tokens on all forms
- [ ] Security headers set
- [ ] Session security enabled
- [ ] Error handling configured
- [ ] File permissions set correctly (0640 for files, 0750 for dirs)
- [ ] SQL injection protection (if database added)
- [ ] XSS protection via output encoding
- [ ] Path traversal prevention
- [ ] File upload validation
- [ ] Rate limiting (if applicable)
- [ ] Authentication/authorization (if applicable)
- [ ] Logging configured
- [ ] Testing completed

## Common Security Mistakes to Avoid

❌ **DON'T:**
- Use user input directly in file paths
- Trust `$_POST`, `$_GET`, `$_FILES` without validation
- Display detailed error messages to users
- Store sensitive data in cookies without encryption
- Allow unlimited file uploads
- Execute user-supplied code
- Use `eval()` or `exec()` with user input
- Expose internal paths or configurations
- Use predictable IDs or tokens
- Skip CSRF validation
- Trust client-side validation alone

✅ **DO:**
- Validate all inputs server-side
- Use parameterized queries (when database is added)
- Encode all outputs
- Use CSRF tokens on forms
- Set secure session cookies
- Log security events
- Limit file sizes and types
- Check permissions before operations
- Use HTTPS in production
- Keep dependencies updated
- Follow principle of least privilege
- Implement defense in depth

## Production Deployment Checklist

1. **SSL/TLS Configuration**
   - [ ] SSL certificate installed
   - [ ] HTTPS enforced (uncomment in .htaccess)
   - [ ] HSTS header configured

2. **PHP Configuration (php.ini)**
   - [ ] `display_errors = Off`
   - [ ] `error_reporting = E_ALL`
   - [ ] `log_errors = On`
   - [ ] `expose_php = Off`
   - [ ] `session.cookie_secure = 1`
   - [ ] `session.cookie_httponly = 1`
   - [ ] `upload_max_filesize = 30M`
   - [ ] `post_max_size = 35M`
   - [ ] `max_execution_time = 300`

3. **File Permissions**
   ```bash
   chmod 640 config.php
   chmod 750 storage/
   chmod 755 app/public/
   ```

4. **Server Configuration**
   - [ ] .htaccess files in place
   - [ ] storage/ directory not web-accessible
   - [ ] Error logs not web-accessible
   - [ ] Directory listing disabled

5. **Security Monitoring**
   - [ ] Log monitoring configured
   - [ ] Backup system in place
   - [ ] Update schedule established

## Emergency Response

### If Security Incident Occurs:

1. **Immediate Actions**
   - Disable affected functionality
   - Check logs: `storage/logs/error.log`
   - Identify scope of breach
   - Document everything

2. **Investigation**
   - Review recent access logs
   - Check for unauthorized file modifications
   - Verify database integrity (if applicable)
   - Check for backdoors

3. **Remediation**
   - Patch vulnerability
   - Change all credentials
   - Rotate session keys
   - Update dependencies

4. **Prevention**
   - Review and update security measures
   - Conduct security audit
   - Update this documentation

## Support Resources

- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **PHP Security Guide:** https://www.php.net/manual/en/security.php
- **Security Headers:** https://securityheaders.com/
- **CVE Database:** https://cve.mitre.org/

---

**Last Updated:** January 11, 2026
