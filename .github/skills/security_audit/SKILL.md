---
name: security-audit
description: tools and instructions for performing a security audit and penetration testing on the KMP application.
---

# Security Audit and Penetration Testing Instructions

Perform comprehensive security testing of the KMP application using both static code analysis and dynamic terminal-based testing.

## Application Context

- **Stack**: CakePHP 5.x backend, Stimulus.js frontend, MySQL database
- **Application URL**: `http://localhost:8080`
- **Test Password**: `TestPassword` (for all dev users)
- **App Directory**: `/workspaces/KMP/app`
- **Reports Directory**: `/workspaces/KMP/security-reports`

## Test User Credentials for Authorization Testing

- **admin@amp.ansteorra.org** - Super admin (full access)
- **iris@ampdemo.com** - Basic user (minimal permissions)
- **bryce@ampdemo.com** - Local Seneschal (moderate permissions)
- **eirik@ampdemo.com** - Kingdom Seneschal (elevated permissions)

## Security Testing Phases

### Phase 1: Static Code Analysis

Analyze the codebase for security vulnerabilities without executing code.

#### 1.1 SQL Injection Vulnerabilities

Search for raw SQL queries and unsafe database operations:

```bash
# Find raw SQL queries that might be vulnerable
grep -rn "query(" app/src/ --include="*.php"
grep -rn "\$this->connection" app/src/ --include="*.php"
grep -rn "execute(" app/src/ --include="*.php"

# Check for string concatenation in queries
grep -rn "WHERE.*\\\$" app/src/ --include="*.php"
grep -rn "SELECT.*\\\$" app/src/ --include="*.php"
```

Look for:
- Direct variable interpolation in SQL strings
- Missing parameter binding
- Dynamic table/column names without whitelisting

#### 1.2 Cross-Site Scripting (XSS)

Search for unescaped output and unsafe JavaScript:

```bash
# Find potentially unescaped PHP output
grep -rn "<?=" app/templates/ --include="*.php" | grep -v " h("
grep -rn "echo \$" app/src/ --include="*.php"

# Check for dangerous JavaScript patterns
grep -rn "innerHTML" app/assets/js/ --include="*.js"
grep -rn "document.write" app/assets/js/ --include="*.js"
grep -rn "eval(" app/assets/js/ --include="*.js"
```

Look for:
- Output without `h()` helper function
- Direct DOM manipulation with user input
- Unsafe template rendering

#### 1.3 Authentication & Session Security

```bash
# Check authentication configuration
cat app/src/Application.php | grep -A 50 "getAuthenticationService"

# Find session handling
grep -rn "Session" app/src/ --include="*.php"
grep -rn "cookie" app/config/ --include="*.php"

# Check password handling
grep -rn "password" app/src/ --include="*.php"
grep -rn "bcrypt\|hash\|PASSWORD_DEFAULT" app/src/ --include="*.php"
```

Look for:
- Weak session configuration
- Missing CSRF protection
- Insecure password storage
- Session fixation vulnerabilities

#### 1.4 Authorization Bypass

```bash
# Check policy implementations
find app/src/Policy -name "*.php" -exec cat {} \;

# Find authorization checks in controllers
grep -rn "authorize\|canAccess\|isAuthorized" app/src/Controller/ --include="*.php"

# Check for missing authorization
grep -rn "public function" app/src/Controller/ --include="*.php" | head -50
```

Look for:
- Controllers without authorization checks
- IDOR (Insecure Direct Object Reference) vulnerabilities
- Privilege escalation paths

#### 1.5 File Upload Vulnerabilities

```bash
# Find file upload handling
grep -rn "upload\|getClientFilename\|moveTo" app/src/ --include="*.php"
grep -rn "file_put_contents\|move_uploaded_file" app/src/ --include="*.php"

# Check allowed file types
grep -rn "mime\|extension\|ALLOWED" app/src/ --include="*.php"
```

Look for:
- Missing file type validation
- Path traversal in filenames
- Executable file uploads

#### 1.6 Sensitive Data Exposure

```bash
# Find hardcoded credentials or secrets
grep -rn "password\s*=\s*['\"]" app/src/ --include="*.php"
grep -rn "api_key\|secret\|token" app/src/ --include="*.php"
grep -rn "API_KEY\|SECRET" app/config/ --include="*.php"

# Check .env file for sensitive data
cat app/config/.env 2>/dev/null || echo ".env not found"

# Find logging of sensitive data
grep -rn "Log::" app/src/ --include="*.php" | grep -i "password\|token\|secret"
```

#### 1.7 Command Injection

```bash
# Find shell command execution
grep -rn "exec(\|shell_exec\|system(\|passthru\|popen\|proc_open" app/src/ --include="*.php"
grep -rn "``" app/src/ --include="*.php"
```

#### 1.8 Dependency Vulnerabilities

```bash
# Check PHP dependencies
cd /workspaces/KMP/app && composer audit

# Check JavaScript dependencies
cd /workspaces/KMP/app && npm audit 2>/dev/null || echo "No package-lock.json"
```

### Phase 2: Dynamic Security Testing

Execute runtime tests against the running application.

#### 2.1 Prerequisite Checks

```bash
# Verify application is running
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080

# Create reports directory
mkdir -p /workspaces/KMP/security-reports
```

#### 2.2 Authentication Testing

Test login functionality for common vulnerabilities:

```bash
# Test for user enumeration
curl -s -X POST http://localhost:8080/members/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=nonexistent@test.com&password=wrong" | grep -i "error\|invalid\|incorrect"

curl -s -X POST http://localhost:8080/members/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=admin@amp.ansteorra.org&password=wrong" | grep -i "error\|invalid\|incorrect"

# Test for brute force protection (try 5 rapid requests)
for i in {1..5}; do
  curl -s -X POST http://localhost:8080/members/login \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "email=admin@amp.ansteorra.org&password=wrong$i" -o /dev/null -w "%{http_code}\n"
done
```

#### 2.3 SQL Injection Testing

```bash
# Test common SQL injection patterns
curl -s "http://localhost:8080/members/view/1'" | head -20
curl -s "http://localhost:8080/members/view/1%20OR%201=1" | head -20
curl -s "http://localhost:8080/members?search=test'%20OR%20'1'='1" | head -20
```

#### 2.4 XSS Testing

```bash
# Test reflected XSS
curl -s "http://localhost:8080/members?search=<script>alert(1)</script>" | grep -o "<script>alert(1)</script>"

# Test for proper encoding
curl -s "http://localhost:8080/members?search=%3Cscript%3Ealert(1)%3C/script%3E" | grep -o "&lt;script&gt;"
```

#### 2.5 CSRF Protection

```bash
# Check for CSRF tokens in forms
curl -s http://localhost:8080/members/login | grep -i "csrf\|_token\|_csrfToken"

# Attempt POST without CSRF token (should fail)
curl -s -X POST http://localhost:8080/members/add \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "name=test" -w "%{http_code}"
```

#### 2.6 Directory Traversal

```bash
# Test path traversal
curl -s "http://localhost:8080/../../../etc/passwd" -o /dev/null -w "%{http_code}"
curl -s "http://localhost:8080/..%2F..%2F..%2Fetc%2Fpasswd" -o /dev/null -w "%{http_code}"

# Check for exposed sensitive files
curl -s "http://localhost:8080/.env" -o /dev/null -w "%{http_code}"
curl -s "http://localhost:8080/config/app.php" -o /dev/null -w "%{http_code}"
curl -s "http://localhost:8080/.git/config" -o /dev/null -w "%{http_code}"
```

#### 2.7 Security Headers Check

```bash
# Check response headers
curl -s -I http://localhost:8080 | grep -iE "x-frame-options|x-content-type|x-xss-protection|strict-transport|content-security-policy"
```

#### 2.8 IDOR Testing (Requires Authentication)

```bash
# Login as basic user and try to access admin resources
# First get a session cookie (manual step or use browser automation)
curl -c cookies.txt -X POST http://localhost:8080/members/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=iris@ampdemo.com&password=TestPassword" -L

# Try to access another user's data
curl -b cookies.txt "http://localhost:8080/members/view/1" -o /dev/null -w "%{http_code}"
curl -b cookies.txt "http://localhost:8080/members/edit/1" -o /dev/null -w "%{http_code}"

# Cleanup
rm -f cookies.txt
```

### Phase 3: Automated Security Scanners

Use available security tools for comprehensive scanning.

#### 3.1 PHP Security Checker

```bash
cd /workspaces/KMP/app
local-php-security-checker 2>/dev/null || echo "local-php-security-checker not installed"
```

#### 3.2 OWASP Dependency Check

```bash
dependency-check --project "KMP" \
  --scan "/workspaces/KMP/app" \
  --out "/workspaces/KMP/security-reports/dependency-check" \
  --format HTML 2>/dev/null || echo "dependency-check not installed"
```

#### 3.3 Nikto Web Scanner

```bash
nikto -h http://localhost:8080 \
  -o /workspaces/KMP/security-reports/nikto-report.html \
  -Format html 2>/dev/null || echo "nikto not installed"
```

#### 3.4 Nuclei Vulnerability Scanner

```bash
nuclei -u http://localhost:8080 \
  -o /workspaces/KMP/security-reports/nuclei-report.txt \
  -silent 2>/dev/null || echo "nuclei not installed"
```

### Phase 4: CakePHP-Specific Security Checks

#### 4.1 Debug Mode Check

```bash
# Ensure debug mode is off in production config
grep -r "debug" app/config/app.php app/config/app_local.php 2>/dev/null
```

#### 4.2 Security Component Configuration

```bash
# Check Security component usage
grep -rn "Security" app/src/Controller/ --include="*.php"
grep -rn "FormProtection" app/src/Controller/ --include="*.php"
```

#### 4.3 Safe Query Practices

```bash
# Verify ORM usage (safe) vs raw queries (potentially unsafe)
echo "=== ORM Usage (Safe) ==="
grep -c "->find\|->get\|->save\|->delete" app/src/Model/Table/*.php 2>/dev/null || echo "No Table files found"

echo "=== Raw Queries (Review Needed) ==="
grep -rn "getConnection\|query(" app/src/ --include="*.php"
```

## Reporting Template

When reporting findings, use this format:

### Vulnerability Report

| Severity | Category | Location | Description | Remediation |
|----------|----------|----------|-------------|-------------|
| CRITICAL | SQL Injection | src/Controller/X.php:42 | Raw query with user input | Use parameter binding |
| HIGH | XSS | templates/Members/view.php:15 | Unescaped output | Use h() helper |
| MEDIUM | Auth | src/Application.php | Weak session timeout | Increase session security |
| LOW | Headers | N/A | Missing X-Frame-Options | Add security headers |

### Risk Levels

- **CRITICAL**: Immediate exploitation possible, data breach risk
- **HIGH**: Significant security flaw, needs priority fix
- **MEDIUM**: Security weakness, should be addressed
- **LOW**: Minor issue, best practice recommendation
- **INFO**: Informational finding, no direct security impact

## Testing Workflow

1. **Start with Phase 1** - Analyze code without running app
2. **Verify app is running** - Check `http://localhost:8080` responds
3. **Run Phase 2** - Dynamic tests against running app
4. **Run Phase 3** - Automated scanners if available
5. **Run Phase 4** - CakePHP-specific checks
6. **Compile Report** - Document all findings with severity ratings
7. **Suggest Remediation** - Provide fix recommendations for each issue

## Security Testing Best Practices

- Never test in production without authorization
- Document all findings immediately
- Verify false positives before reporting
- Prioritize findings by risk level
- Provide actionable remediation steps
- Re-test after fixes are applied
