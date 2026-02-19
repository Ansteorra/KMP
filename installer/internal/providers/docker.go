package providers

import (
	"bytes"
	"compress/gzip"
	"crypto/rand"
	_ "embed"
	"encoding/hex"
	"fmt"
	"io"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"text/template"
	"time"

	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/health"
)

//go:embed templates/docker-compose.yml.tmpl
var composeTemplate string

//go:embed templates/Caddyfile.tmpl
var caddyTemplate string

//go:embed templates/env.tmpl
var envTemplate string

// DockerProvider implements Provider for Docker Compose deployments.
type DockerProvider struct {
	cfg *config.Deployment
	dir string // deployment directory (compose files live here)
}

// NewDockerProvider creates a provider for local Docker Compose deployments.
func NewDockerProvider(cfg *config.Deployment) *DockerProvider {
	dir := ""
	if cfg != nil {
		dir = cfg.ComposeDir
	}
	if dir == "" {
		dir = filepath.Join(config.DefaultConfigDir(), "deployments", "default")
	}
	return &DockerProvider{cfg: cfg, dir: dir}
}

func (d *DockerProvider) Name() string {
	return "Docker Compose (Local)"
}

func (d *DockerProvider) Detect() bool {
	_, err := exec.LookPath("docker")
	return err == nil
}

func (d *DockerProvider) Prerequisites() []Prerequisite {
	prereqs := []Prerequisite{
		{
			Name:        "Docker",
			Description: "Docker Engine must be installed",
			Met:         exec.Command("docker", "info").Run() == nil,
			InstallHint: "Install Docker: https://docs.docker.com/engine/install/",
		},
		{
			Name:        "Docker Compose v2",
			Description: "Docker Compose v2 plugin is required",
			Met:         exec.Command("docker", "compose", "version").Run() == nil,
			InstallHint: "Docker Compose v2 is included with Docker Desktop, or install the plugin: https://docs.docker.com/compose/install/",
		},
		{
			Name:        "Port 80 available",
			Description: "HTTP port must be free for the reverse proxy",
			Met:         portAvailable(80),
			InstallHint: "Stop any service using port 80 (e.g. Apache, nginx)",
		},
		{
			Name:        "Port 443 available",
			Description: "HTTPS port must be free for the reverse proxy",
			Met:         portAvailable(443),
			InstallHint: "Stop any service using port 443",
		},
	}
	return prereqs
}

func (d *DockerProvider) Install(cfg *DeployConfig) error {
	// Determine database type
	dbType := "bundled"
	if cfg.DatabaseDSN != "" {
		dbType = "external"
	}

	// Create deployment directory
	if err := os.MkdirAll(d.dir, 0750); err != nil {
		return fmt.Errorf("creating deployment directory: %w", err)
	}

	// Template data shared across all templates
	data := templateData{
		Image:          cfg.Image,
		ImageTag:       cfg.ImageTag,
		Domain:         cfg.Domain,
		DatabaseType:   dbType,
		DatabaseDSN:    cfg.DatabaseDSN,
		SecuritySalt:   generateRandomString(32),
		DBRootPassword: generateRandomString(16),
		DBPassword:     generateRandomString(16),
		SMTPHost:       valueOrDefault(cfg.StorageConfig["smtp_host"], ""),
		SMTPPort:       valueOrDefault(cfg.StorageConfig["smtp_port"], "587"),
		SMTPUser:       valueOrDefault(cfg.StorageConfig["smtp_user"], ""),
		SMTPPass:       valueOrDefault(cfg.StorageConfig["smtp_pass"], ""),
		StorageType:    cfg.StorageType,
	}

	// Write .env
	if err := renderToFile(envTemplate, data, filepath.Join(d.dir, ".env"), 0600); err != nil {
		return fmt.Errorf("writing .env: %w", err)
	}

	// Write docker-compose.yml
	if err := renderToFile(composeTemplate, data, filepath.Join(d.dir, "docker-compose.yml"), 0644); err != nil {
		return fmt.Errorf("writing docker-compose.yml: %w", err)
	}

	// Write Caddyfile
	if err := renderToFile(caddyTemplate, data, filepath.Join(d.dir, "Caddyfile"), 0644); err != nil {
		return fmt.Errorf("writing Caddyfile: %w", err)
	}

	// Pull images
	if out, err := runDockerCompose(d.dir, "pull"); err != nil {
		return fmt.Errorf("docker compose pull: %s\n%w", out, err)
	}

	// Start services
	if out, err := runDockerCompose(d.dir, "up", "-d"); err != nil {
		return fmt.Errorf("docker compose up: %s\n%w", out, err)
	}

	// Wait for health
	if err := d.waitForHealthy(cfg.Domain, 120*time.Second); err != nil {
		return fmt.Errorf("health check: %w", err)
	}

	// Persist deployment config
	return d.saveDeployment(cfg)
}

func (d *DockerProvider) Update(version string) error {
	// Update .env image tag
	envPath := filepath.Join(d.dir, ".env")
	if err := replaceEnvValue(envPath, d.cfg.ImageTag, version); err != nil {
		return fmt.Errorf("updating .env: %w", err)
	}

	previousTag := d.cfg.ImageTag
	d.cfg.ImageTag = version

	if out, err := runDockerCompose(d.dir, "pull"); err != nil {
		return fmt.Errorf("docker compose pull: %s\n%w", out, err)
	}

	if out, err := runDockerCompose(d.dir, "up", "-d"); err != nil {
		// Attempt rollback on failure
		_ = replaceEnvValue(envPath, version, previousTag)
		d.cfg.ImageTag = previousTag
		return fmt.Errorf("docker compose up: %s\n%w", out, err)
	}

	domain := d.cfg.Domain
	if domain == "" {
		domain = "localhost"
	}
	if err := d.waitForHealthy(domain, 120*time.Second); err != nil {
		return fmt.Errorf("health check after update: %w", err)
	}

	// Update saved config
	appCfg, err := config.Load()
	if err != nil {
		return err
	}
	if dep, ok := appCfg.Deployments["default"]; ok {
		dep.ImageTag = version
		return appCfg.Save()
	}
	return nil
}

func (d *DockerProvider) Status() (*Status, error) {
	domain := d.cfg.Domain
	if domain == "" {
		domain = "localhost"
	}

	scheme := "https"
	if domain == "localhost" {
		scheme = "http"
	}
	baseURL := fmt.Sprintf("%s://%s", scheme, domain)

	hr, err := health.Check(baseURL)

	st := &Status{
		Domain:   domain,
		Provider: d.Name(),
		Channel:  d.cfg.Channel,
		Version:  d.cfg.ImageTag,
	}

	if err == nil {
		st.Running = true
		st.Healthy = hr.IsHealthy()
		st.DBConnected = hr.DB
		st.CacheOK = hr.Cache
		st.Version = hr.Version
	}

	// Try to get uptime from docker compose ps
	if out, err := runDockerCompose(d.dir, "ps", "--format", "{{.Status}}"); err == nil {
		lines := strings.TrimSpace(out)
		if lines != "" {
			st.Uptime = strings.Split(lines, "\n")[0]
		}
	}

	return st, nil
}

func (d *DockerProvider) Logs(follow bool) (io.ReadCloser, error) {
	args := []string{"compose", "logs", "--tail", "100"}
	if follow {
		args = append(args, "-f")
	}

	cmd := exec.Command("docker", args...)
	cmd.Dir = d.dir

	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return nil, err
	}
	cmd.Stderr = cmd.Stdout

	if err := cmd.Start(); err != nil {
		return nil, fmt.Errorf("starting docker compose logs: %w", err)
	}

	return stdout, nil
}

func (d *DockerProvider) Backup() (*BackupResult, error) {
	backupDir := filepath.Join(d.dir, "backups")
	if err := os.MkdirAll(backupDir, 0750); err != nil {
		return nil, fmt.Errorf("creating backup directory: %w", err)
	}

	ts := time.Now().UTC().Format("20060102-150405")
	filename := fmt.Sprintf("%s.sql.gz", ts)
	backupPath := filepath.Join(backupDir, filename)

	// Dump database
	dumpOut, err := runDockerCompose(d.dir, "exec", "-T", "db",
		"mysqldump", "--all-databases", "--single-transaction")
	if err != nil {
		return nil, fmt.Errorf("database dump failed: %w", err)
	}

	// Compress and write
	f, err := os.Create(backupPath)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	gz := gzip.NewWriter(f)
	if _, err := gz.Write([]byte(dumpOut)); err != nil {
		return nil, err
	}
	if err := gz.Close(); err != nil {
		return nil, err
	}

	info, _ := f.Stat()
	size := int64(0)
	if info != nil {
		size = info.Size()
	}

	return &BackupResult{
		ID:        ts,
		Timestamp: ts,
		Size:      size,
		Location:  backupPath,
	}, nil
}

func (d *DockerProvider) Restore(backupID string) error {
	backupPath := filepath.Join(d.dir, "backups", backupID+".sql.gz")
	if _, err := os.Stat(backupPath); err != nil {
		return fmt.Errorf("backup not found: %s", backupID)
	}

	// Read and decompress
	f, err := os.Open(backupPath)
	if err != nil {
		return err
	}
	defer f.Close()

	gz, err := gzip.NewReader(f)
	if err != nil {
		return fmt.Errorf("decompressing backup: %w", err)
	}
	defer gz.Close()

	sqlData, err := io.ReadAll(gz)
	if err != nil {
		return fmt.Errorf("reading backup: %w", err)
	}

	// Pipe SQL into mysql
	cmd := exec.Command("docker", "compose", "exec", "-T", "db", "mysql")
	cmd.Dir = d.dir
	cmd.Stdin = bytes.NewReader(sqlData)

	var out bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &out

	if err := cmd.Run(); err != nil {
		return fmt.Errorf("restore failed: %s\n%w", out.String(), err)
	}

	return nil
}

func (d *DockerProvider) Rollback() error {
	appCfg, err := config.Load()
	if err != nil {
		return err
	}

	dep, ok := appCfg.Deployments["default"]
	if !ok {
		return fmt.Errorf("no deployment found to rollback")
	}

	// The current tag is the one we want to revert from;
	// we need the previous tag stored in config.
	previousTag := dep.ImageTag
	if previousTag == "" || previousTag == d.cfg.ImageTag {
		return fmt.Errorf("no previous version available for rollback")
	}

	envPath := filepath.Join(d.dir, ".env")
	if err := replaceEnvValue(envPath, d.cfg.ImageTag, previousTag); err != nil {
		return fmt.Errorf("updating .env for rollback: %w", err)
	}

	if out, err := runDockerCompose(d.dir, "pull"); err != nil {
		return fmt.Errorf("docker compose pull: %s\n%w", out, err)
	}

	if out, err := runDockerCompose(d.dir, "up", "-d"); err != nil {
		return fmt.Errorf("docker compose up: %s\n%w", out, err)
	}

	d.cfg.ImageTag = previousTag
	dep.ImageTag = previousTag
	return appCfg.Save()
}

func (d *DockerProvider) Destroy() error {
	out, err := runDockerCompose(d.dir, "down", "-v")
	if err != nil {
		return fmt.Errorf("docker compose down: %s\n%w", out, err)
	}
	return nil
}

// --- helpers ----------------------------------------------------------------

// templateData holds values interpolated into the embedded templates.
type templateData struct {
	Image          string
	ImageTag       string
	Domain         string
	DatabaseType   string // "bundled" or "external"
	DatabaseDSN    string
	SecuritySalt   string
	DBRootPassword string
	DBPassword     string
	SMTPHost       string
	SMTPPort       string
	SMTPUser       string
	SMTPPass       string
	StorageType    string
}

func generateRandomString(length int) string {
	b := make([]byte, length)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}

func runDockerCompose(dir string, args ...string) (string, error) {
	cmd := exec.Command("docker", append([]string{"compose"}, args...)...)
	cmd.Dir = dir
	var out bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &out
	err := cmd.Run()
	return out.String(), err
}

func renderToFile(tmplStr string, data templateData, path string, perm os.FileMode) error {
	t, err := template.New("").Parse(tmplStr)
	if err != nil {
		return fmt.Errorf("parsing template: %w", err)
	}

	var buf bytes.Buffer
	if err := t.Execute(&buf, data); err != nil {
		return fmt.Errorf("executing template: %w", err)
	}

	return os.WriteFile(path, buf.Bytes(), perm)
}

func portAvailable(port int) bool {
	ln, err := net.Listen("tcp", fmt.Sprintf(":%d", port))
	if err != nil {
		return false
	}
	ln.Close()
	return true
}

func valueOrDefault(val, fallback string) string {
	if val != "" {
		return val
	}
	return fallback
}

// replaceEnvValue does a simple string replacement inside the .env file,
// swapping oldTag for newTag in image tag references.
func replaceEnvValue(envPath, oldTag, newTag string) error {
	data, err := os.ReadFile(envPath)
	if err != nil {
		return err
	}
	updated := strings.ReplaceAll(string(data), oldTag, newTag)
	return os.WriteFile(envPath, []byte(updated), 0600)
}

func (d *DockerProvider) waitForHealthy(domain string, timeout time.Duration) error {
	scheme := "https"
	if domain == "localhost" {
		scheme = "http"
	}
	baseURL := fmt.Sprintf("%s://%s", scheme, domain)

	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		resp, err := health.Check(baseURL)
		if err == nil && resp.IsHealthy() {
			return nil
		}
		time.Sleep(3 * time.Second)
	}
	return fmt.Errorf("timed out waiting for %s to become healthy", baseURL)
}

func (d *DockerProvider) saveDeployment(cfg *DeployConfig) error {
	appCfg, err := config.Load()
	if err != nil {
		return err
	}

	name := cfg.Name
	if name == "" {
		name = "default"
	}

	appCfg.Deployments[name] = &config.Deployment{
		Provider:        "docker",
		Channel:         cfg.Channel,
		Domain:          cfg.Domain,
		Image:           cfg.Image,
		ImageTag:        cfg.ImageTag,
		ComposeDir:      d.dir,
		DatabaseDSN:     cfg.DatabaseDSN,
		StorageType:     cfg.StorageType,
		StorageConfig:   cfg.StorageConfig,
		BackupEnabled:   cfg.BackupConfig.Enabled,
		BackupSchedule:  cfg.BackupConfig.Schedule,
		BackupRetention: cfg.BackupConfig.RetentionDays,
	}

	return appCfg.Save()
}
