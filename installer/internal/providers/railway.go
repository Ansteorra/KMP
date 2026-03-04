package providers

import (
	"encoding/json"
	"fmt"
	"io"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/health"
)

// RailwayProvider deploys KMP to Railway with managed MySQL.
type RailwayProvider struct {
	cfg *config.Deployment
}

// NewRailwayProvider creates a new Railway provider.
func NewRailwayProvider(cfg *config.Deployment) *RailwayProvider {
	return &RailwayProvider{cfg: cfg}
}

func (r *RailwayProvider) Name() string { return "Railway" }

func (r *RailwayProvider) Detect() bool {
	return commandExists("railway")
}

func (r *RailwayProvider) Prerequisites() []Prerequisite {
	cliInstalled := commandExists("railway")

	authenticated := false
	if cliInstalled {
		_, err := runCommand("railway", "whoami")
		authenticated = err == nil
	}

	return []Prerequisite{
		{
			Name:        "Railway CLI",
			Description: "railway CLI must be installed",
			Met:         cliInstalled,
			InstallHint: "Install: npm install -g @railway/cli",
		},
		{
			Name:        "Railway authentication",
			Description: "Must be logged in to Railway",
			Met:         authenticated,
			InstallHint: "Run: railway login",
		},
	}
}

func (r *RailwayProvider) Install(cfg *DeployConfig) error {
	projectName := railwayProjectName(cfg)
	appServiceName := railwayDefaultAppServiceName
	imageRef := railwayImageRef(cfg.Image, cfg.ImageTag)
	if cfg.StorageConfig == nil {
		cfg.StorageConfig = map[string]string{}
	}
	cfg.StorageConfig["railway_project"] = projectName
	useManagedMySQL := strings.TrimSpace(cfg.DatabaseDSN) == ""
	useManagedRedis := cfg.CacheEngine == "redis" && strings.TrimSpace(cfg.RedisURL) == ""

	// Initialize/link project.
	if err := runRailwayVariants(
		[][]string{
			{"init", "--name", projectName},
			{"new", "--name", projectName},
		},
		"failed to initialize Railway project",
	); err != nil {
		return err
	}
	if err := runRailwayVariants(
		[][]string{
			{"add", "--service", appServiceName},
			{"add", "-s", appServiceName},
		},
		"failed to create Railway app service",
	); err != nil {
		return err
	}

	// Provision managed MySQL service when no external DATABASE_URL was provided.
	if useManagedMySQL {
		if err := runRailwayVariants(
			[][]string{
				{"add", "--database", "mysql"},
				{"add", "-d", "mysql"},
				{"add", "mysql"},
			},
			"failed to provision Railway MySQL service",
		); err != nil {
			return err
		}
	}
	if useManagedRedis {
		if err := runRailwayVariants(
			[][]string{
				{"add", "--database", "redis"},
				{"add", "-d", "redis"},
				{"add", "redis"},
			},
			"failed to provision Railway Redis service",
		); err != nil {
			return err
		}
	}

	domain := strings.TrimSpace(cfg.Domain)
	if domain == "" || domain == "localhost" {
		generatedDomain, err := railwayGenerateDomain(appServiceName)
		if err != nil {
			return err
		}
		domain = generatedDomain
		cfg.Domain = generatedDomain
	}

	requireHTTPS := "true"
	baseURL := "https://" + domain
	if domain == "localhost" {
		requireHTTPS = "false"
		baseURL = "http://localhost"
	}

	cacheEngine := "apcu"
	if cfg.CacheEngine == "redis" {
		cacheEngine = "redis"
	}
	dbConnection := "mysql"
	if strings.HasPrefix(strings.ToLower(strings.TrimSpace(cfg.DatabaseDSN)), "postgres://") ||
		strings.HasPrefix(strings.ToLower(strings.TrimSpace(cfg.DatabaseDSN)), "postgresql://") {
		dbConnection = "pgsql"
	}

	variables := []string{
		"APP_NAME=KMP",
		"DEBUG=false",
		"REQUIRE_HTTPS=" + requireHTTPS,
		"APP_FULL_BASE_URL=" + baseURL,
		"KMP_DEPLOY_PROVIDER=railway",
		"SECURITY_SALT=" + generateRandomString(32),
		"CACHE_ENGINE=" + cacheEngine,
		"DB_CONNECTION=" + dbConnection,
		"RAILPACK_PHP_ROOT_DIR=/app/webroot",
	}
	if useManagedMySQL {
		variables = append(
			variables,
			"MYSQL_HOST=${{MySQL.MYSQLHOST}}",
			"MYSQL_PORT=${{MySQL.MYSQLPORT}}",
			"MYSQL_DB_NAME=${{MySQL.MYSQLDATABASE}}",
			"MYSQL_USERNAME=${{MySQL.MYSQLUSER}}",
			"MYSQL_PASSWORD=${{MySQL.MYSQLPASSWORD}}",
			"DATABASE_URL=mysql://${{MySQL.MYSQLUSER}}:${{MySQL.MYSQLPASSWORD}}@${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}/${{MySQL.MYSQLDATABASE}}",
		)
	} else {
		variables = append(variables, "DATABASE_URL="+cfg.DatabaseDSN)
		if cfg.MySQLSSL {
			variables = append(variables, "MYSQL_SSL=true")
		}
	}
	if cfg.CacheEngine == "redis" && cfg.RedisURL != "" {
		variables = append(variables, "REDIS_URL="+cfg.RedisURL)
	} else if useManagedRedis {
		variables = append(variables, "REDIS_URL=redis://${{Redis.REDISUSER}}:${{Redis.REDISPASSWORD}}@${{Redis.REDISHOST}}:${{Redis.REDISPORT}}")
	}

	if err := runRailwayVariants(
		[][]string{
			append(append([]string{"variable", "set"}, variables...), "-s", appServiceName),
			append(append([]string{"variables", "set"}, variables...), "-s", appServiceName),
			append(append([]string{"vars", "set"}, variables...), "-s", appServiceName),
		},
		"failed setting Railway environment variables",
	); err != nil {
		return err
	}

	// Deploy from a minimal Dockerfile that references the shared image.
	deployPath, cleanup, err := railwayPrepareImageDeployPath(imageRef)
	if err != nil {
		return err
	}
	defer cleanup()

	if err := runRailwayVariants(
		[][]string{
			{"up", deployPath, "--path-as-root", "--detach", "-s", appServiceName},
			{"up", deployPath, "--path-as-root", "-s", appServiceName},
		},
		"failed to deploy Railway app service",
	); err != nil {
		return err
	}
	if err := runRailwayMigrations(appServiceName); err != nil {
		return err
	}

	return r.saveDeployment(cfg)
}

func (r *RailwayProvider) Update(version string) error {
	if r.cfg == nil {
		return fmt.Errorf("no existing Railway deployment config found")
	}

	appServiceName := railwayAppServiceNameFromDeployment(r.cfg)
	imageRepo := strings.TrimSpace(r.cfg.Image)
	if imageRepo == "" {
		imageRepo = "ghcr.io/jhandel/kmp"
	}
	imageTag := strings.TrimSpace(version)
	if imageTag == "" {
		imageTag = strings.TrimSpace(r.cfg.ImageTag)
	}
	imageRef := railwayImageRef(imageRepo, imageTag)
	deployPath, cleanup, err := railwayPrepareImageDeployPath(imageRef)
	if err != nil {
		return err
	}
	defer cleanup()

	if err := runRailwayVariants(
		[][]string{
			{"up", deployPath, "--path-as-root", "--detach", "-s", appServiceName},
			{"up", deployPath, "--path-as-root", "-s", appServiceName},
		},
		"failed to update Railway deployment",
	); err != nil {
		return err
	}
	if err := runRailwayMigrations(appServiceName); err != nil {
		return err
	}

	appCfg, err := config.Load()
	if err != nil {
		return err
	}
	if dep, ok := appCfg.Deployments["default"]; ok {
		if strings.TrimSpace(version) != "" {
			dep.ImageTag = version
		}
		return appCfg.Save()
	}

	return nil
}

func (r *RailwayProvider) Status() (*Status, error) {
	if r.cfg == nil {
		return nil, fmt.Errorf("no Railway deployment config found")
	}

	out, err := runCommand("railway", "status")
	if err != nil {
		return nil, fmt.Errorf("failed to read Railway status: %w", err)
	}

	running := false
	lower := strings.ToLower(out)
	for _, marker := range []string{"running", "deployed", "active", "healthy"} {
		if strings.Contains(lower, marker) {
			running = true
			break
		}
	}

	st := &Status{
		Running:  running,
		Version:  r.cfg.ImageTag,
		Channel:  r.cfg.Channel,
		Domain:   r.cfg.Domain,
		Provider: "Railway",
	}

	domain := strings.TrimSpace(r.cfg.Domain)
	if domain != "" {
		scheme := "https"
		if domain == "localhost" {
			scheme = "http"
		}
		healthResp, healthErr := health.Check(fmt.Sprintf("%s://%s", scheme, domain))
		if healthErr == nil {
			st.Healthy = healthResp.IsHealthy()
			st.DBConnected = healthResp.DB
			st.CacheOK = healthResp.Cache
			if healthResp.Version != "" {
				st.Version = healthResp.Version
			}
		}
	}

	return st, nil
}

func (r *RailwayProvider) Logs(follow bool) (io.ReadCloser, error) {
	args := []string{"logs"}
	if serviceName := railwayAppServiceNameFromDeployment(r.cfg); serviceName != "" {
		args = append(args, "-s", serviceName)
	}
	if follow {
		args = append(args, "--follow")
	}

	cmd := exec.Command("railway", args...)
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return nil, err
	}
	cmd.Stderr = cmd.Stdout

	if err := cmd.Start(); err != nil {
		return nil, fmt.Errorf("starting railway logs: %w", err)
	}

	return stdout, nil
}

func (r *RailwayProvider) Backup() (*BackupResult, error) {
	// TODO: Implement Railway MySQL backup via plugin or dump
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Restore(backupID string) error {
	// TODO: Restore Railway MySQL from backup
	return fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Rollback() error {
	// TODO: Redeploy previous version via railway up
	return fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Destroy() error {
	// TODO: railway delete to tear down the project
	return fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func runRailwayVariants(variants [][]string, context string) error {
	var attempts []string
	for _, args := range variants {
		if len(args) == 0 {
			continue
		}
		if _, err := runCommand("railway", args...); err == nil {
			return nil
		} else {
			attempts = append(attempts, fmt.Sprintf("railway %s => %v", strings.Join(args, " "), err))
		}
	}

	if len(attempts) == 0 {
		return fmt.Errorf("%s: no command variants available", context)
	}

	return fmt.Errorf("%s:\n- %s", context, strings.Join(attempts, "\n- "))
}

func waitForRailwaySSH(appServiceName string, maxAttempts int, delay time.Duration) error {
	if maxAttempts < 1 {
		maxAttempts = 1
	}

	var lastErr error
	for attempt := 1; attempt <= maxAttempts; attempt++ {
		if _, err := runCommand(
			"railway",
			"ssh",
			"-s", appServiceName,
			"--",
			"sh",
			"-lc",
			"echo 'kmp-ssh-ready'",
		); err == nil {
			return nil
		} else {
			lastErr = err
		}

		if attempt < maxAttempts {
			time.Sleep(delay)
		}
	}

	return fmt.Errorf(
		"service %q not reachable via Railway SSH after %d attempts: %w",
		appServiceName,
		maxAttempts,
		lastErr,
	)
}

func runRailwayMigrations(appServiceName string) error {
	if err := waitForRailwaySSH(appServiceName, 24, 5*time.Second); err != nil {
		return fmt.Errorf("failed waiting for Railway service readiness before migrations: %w", err)
	}

	migrationCommands := []string{
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate",
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate -p Queue",
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate -p Activities",
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate -p Officers",
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate -p Awards",
		"cd /app && CACHE_ENGINE=apcu php bin/cake.php migrations migrate -p Waivers",
	}

	var failures []string
	for _, migrationCommand := range migrationCommands {
		var commandErr error
		for i := 0; i < 3; i++ {
			if _, err := runCommand(
				"railway",
				"ssh",
				"-s", appServiceName,
				"--",
				"sh",
				"-lc",
				migrationCommand,
			); err == nil {
				commandErr = nil
				break
			} else {
				commandErr = err
				time.Sleep(5 * time.Second)
			}
		}
		if commandErr != nil {
			failures = append(failures, fmt.Sprintf("railway ssh -s %s -- sh -lc '%s' => %v", appServiceName, migrationCommand, commandErr))
		}
	}

	if len(failures) > 0 {
		return fmt.Errorf("failed to run Railway database migrations:\n- %s", strings.Join(failures, "\n- "))
	}

	return nil
}

func railwayGenerateDomain(serviceName string) (string, error) {
	out, err := runCommand("railway", "domain", "-s", serviceName, "--json")
	if err != nil {
		return "", fmt.Errorf("failed to generate Railway domain: %w", err)
	}

	start := strings.Index(out, "{")
	end := strings.LastIndex(out, "}")
	if start < 0 || end < start {
		return "", fmt.Errorf("unexpected Railway domain output: %s", strings.TrimSpace(out))
	}

	var response struct {
		Domain string `json:"domain"`
	}
	if err := json.Unmarshal([]byte(out[start:end+1]), &response); err != nil {
		return "", fmt.Errorf("invalid Railway domain response: %w", err)
	}

	domain := strings.TrimSpace(response.Domain)
	if parsed, err := url.Parse(domain); err == nil && parsed.Host != "" {
		domain = parsed.Host
	}
	domain = strings.TrimPrefix(strings.TrimPrefix(domain, "https://"), "http://")
	domain = strings.TrimRight(domain, "/")
	if domain == "" {
		return "", fmt.Errorf("Railway returned empty domain")
	}

	return domain, nil
}

var invalidRailwayNameChars = regexp.MustCompile(`[^a-z0-9-]`)

const railwayDefaultAppServiceName = "kmp-app"

func railwayProjectName(cfg *DeployConfig) string {
	if cfg != nil && cfg.StorageConfig != nil {
		if project := strings.ToLower(strings.TrimSpace(cfg.StorageConfig["railway_project"])); project != "" {
			return project
		}
	}

	name := strings.ToLower(strings.TrimSpace(cfg.Name))
	if name == "" || name == "default" {
		name = strings.ToLower(strings.TrimSpace(cfg.Domain))
	}
	if name == "" || name == "localhost" {
		name = "kmp-" + generateRandomString(3)
	}

	name = strings.ReplaceAll(name, ".", "-")
	name = invalidRailwayNameChars.ReplaceAllString(name, "-")
	name = strings.Trim(name, "-")
	if name == "" {
		name = "kmp"
	}
	if len(name) > 58 {
		name = strings.Trim(name[:58], "-")
	}

	return name
}

func railwayAppServiceNameFromDeployment(dep *config.Deployment) string {
	if dep != nil && dep.StorageConfig != nil {
		if name := strings.TrimSpace(dep.StorageConfig["railway_app_service"]); name != "" {
			return name
		}
	}

	return railwayDefaultAppServiceName
}

func railwayAppPathFromDeployment(dep *config.Deployment) (string, error) {
	if dep != nil && dep.StorageConfig != nil {
		if appPath := strings.TrimSpace(dep.StorageConfig["railway_app_path"]); appPath != "" {
			return appPath, nil
		}
	}

	return railwayDiscoverAppPath()
}

func railwayDiscoverAppPath() (string, error) {
	candidates := []string{"app", "../app", "../../app"}
	for _, candidate := range candidates {
		if info, err := os.Stat(candidate); err == nil && info.IsDir() {
			return candidate, nil
		}
	}

	if repoRoot, err := runCommand("git", "rev-parse", "--show-toplevel"); err == nil {
		path := filepath.Join(strings.TrimSpace(repoRoot), "app")
		if info, statErr := os.Stat(path); statErr == nil && info.IsDir() {
			return path, nil
		}
	}

	return "", fmt.Errorf("unable to locate app directory for Railway deploy; run from repo root or installer directory")
}

func railwayImageRef(imageRepo, imageTag string) string {
	repo := strings.TrimSpace(imageRepo)
	if repo == "" {
		repo = "ghcr.io/jhandel/kmp"
	}
	tag := strings.TrimSpace(imageTag)
	if tag == "" {
		tag = "latest"
	}

	return fmt.Sprintf("%s:%s", repo, tag)
}

func railwayPrepareImageDeployPath(imageRef string) (string, func(), error) {
	dir, err := os.MkdirTemp("", "kmp-railway-image-")
	if err != nil {
		return "", nil, fmt.Errorf("failed to create temporary Railway deploy directory: %w", err)
	}

	dockerfile := fmt.Sprintf("FROM %s\n", imageRef)
	if writeErr := os.WriteFile(filepath.Join(dir, "Dockerfile"), []byte(dockerfile), 0o644); writeErr != nil {
		_ = os.RemoveAll(dir)
		return "", nil, fmt.Errorf("failed to write temporary Railway Dockerfile: %w", writeErr)
	}

	cleanup := func() {
		_ = os.RemoveAll(dir)
	}

	return dir, cleanup, nil
}

func (r *RailwayProvider) saveDeployment(cfg *DeployConfig) error {
	appCfg, err := config.Load()
	if err != nil {
		return err
	}

	name := cfg.Name
	if name == "" {
		name = "default"
	}

	storageConfig := make(map[string]string, len(cfg.StorageConfig))
	for k, v := range cfg.StorageConfig {
		storageConfig[k] = v
	}
	storageConfig["railway_project"] = railwayProjectName(cfg)
	storageConfig["railway_app_service"] = railwayDefaultAppServiceName

	appCfg.Deployments[name] = &config.Deployment{
		Provider:        "railway",
		Channel:         cfg.Channel,
		Domain:          cfg.Domain,
		Image:           cfg.Image,
		ImageTag:        cfg.ImageTag,
		DatabaseDSN:     cfg.DatabaseDSN,
		MySQLSSL:        cfg.MySQLSSL,
		LocalDBType:     cfg.LocalDBType,
		StorageType:     cfg.StorageType,
		StorageConfig:   storageConfig,
		CacheEngine:     cfg.CacheEngine,
		RedisURL:        cfg.RedisURL,
		BackupEnabled:   cfg.BackupConfig.Enabled,
		BackupSchedule:  cfg.BackupConfig.Schedule,
		BackupRetention: cfg.BackupConfig.RetentionDays,
	}

	return appCfg.Save()
}
