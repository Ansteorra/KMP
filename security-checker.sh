#!/bin/bash
# filepath: /workspaces/KMP/bin/security-check.sh

echo "🔒 Running security checks on KMP application..."

echo "1️⃣ Checking PHP dependencies for vulnerabilities..."
local-php-security-checker

echo "2️⃣ Running OWASP dependency check..."
dependency-check --project "KMP" --scan "/workspaces/KMP" --out "/workspaces/KMP/security-reports/dependency-check"

echo "3️⃣ Running Nikto scan..."
nikto -h localhost:8080 -o /workspaces/KMP/security-reports/nikto-report.html -Format html

echo "4️⃣ Running automated ZAP scan..."
zap -cmd -quickurl http://localhost:8080 -quickout /workspaces/KMP/security-reports/zap-report.html

echo "5️⃣ Running Nuclei with standard templates..."
nuclei -u http://localhost:8080 -o /workspaces/KMP/security-reports/nuclei-report.txt

echo "✅ Security testing complete! Reports are available in /workspaces/KMP/security-reports/"