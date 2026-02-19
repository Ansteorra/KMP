package providers

import (
	"fmt"
	"io"
	"strings"

	"github.com/jhandel/KMP/installer/internal/config"
)

// FlyProvider deploys KMP to Fly.io using Fly Machines + Fly Postgres.
type FlyProvider struct {
	cfg *config.Deployment
}

// NewFlyProvider creates a new Fly.io provider.
func NewFlyProvider(cfg *config.Deployment) *FlyProvider {
	return &FlyProvider{cfg: cfg}
}

func (f *FlyProvider) Name() string { return "Fly.io" }

func (f *FlyProvider) Detect() bool {
	return commandExists("flyctl") || commandExists("fly")
}

// flyCLI returns the available fly CLI command name.
func (f *FlyProvider) flyCLI() string {
	if commandExists("flyctl") {
		return "flyctl"
	}
	return "fly"
}

func (f *FlyProvider) Prerequisites() []Prerequisite {
	cliInstalled := f.Detect()

	// Check authentication
	authenticated := false
	if cliInstalled {
		_, err := runCommand(f.flyCLI(), "auth", "whoami")
		authenticated = err == nil
	}

	return []Prerequisite{
		{
			Name:        "Fly CLI",
			Description: "flyctl or fly CLI must be installed",
			Met:         cliInstalled,
			InstallHint: "Install flyctl: curl -L https://fly.io/install.sh | sh",
		},
		{
			Name:        "Fly authentication",
			Description: "Must be logged in to Fly.io",
			Met:         authenticated,
			InstallHint: "Run: fly auth login",
		},
	}
}

func (f *FlyProvider) Install(cfg *DeployConfig) error {
	cli := f.flyCLI()
	image := fmt.Sprintf("%s:%s", cfg.Image, cfg.ImageTag)
	appName := cfg.Name

	// Launch the app without deploying
	if _, err := runCommand(cli, "launch",
		"--name", appName,
		"--image", image,
		"--no-deploy",
		"--region", "iad",
	); err != nil {
		return fmt.Errorf("fly launch failed: %w", err)
	}

	// Create Fly Postgres cluster
	dbName := appName + "-db"
	if _, err := runCommand(cli, "postgres", "create",
		"--name", dbName,
		"--region", "iad",
		"--vm-size", "shared-cpu-1x",
		"--initial-cluster-size", "1",
		"--volume-size", "1",
	); err != nil {
		return fmt.Errorf("fly postgres create failed: %w", err)
	}

	// Attach the database to the app
	if _, err := runCommand(cli, "postgres", "attach", dbName,
		"--app", appName,
	); err != nil {
		return fmt.Errorf("fly postgres attach failed: %w", err)
	}

	// Set secrets
	// TODO: Generate a proper APP_KEY and additional secrets
	if _, err := runCommand(cli, "secrets", "set",
		"--app", appName,
		"APP_ENV=production",
		fmt.Sprintf("APP_DOMAIN=%s", cfg.Domain),
	); err != nil {
		return fmt.Errorf("fly secrets set failed: %w", err)
	}

	// Deploy the app
	if _, err := runCommand(cli, "deploy",
		"--app", appName,
		"--image", image,
	); err != nil {
		return fmt.Errorf("fly deploy failed: %w", err)
	}

	return nil
}

func (f *FlyProvider) Update(version string) error {
	cli := f.flyCLI()
	image := fmt.Sprintf("%s:%s", f.cfg.Image, version)

	if _, err := runCommand(cli, "deploy",
		"--image", image,
	); err != nil {
		return fmt.Errorf("fly deploy failed: %w", err)
	}
	return nil
}

func (f *FlyProvider) Status() (*Status, error) {
	cli := f.flyCLI()

	out, err := runCommand(cli, "status", "--json")
	if err != nil {
		return nil, fmt.Errorf("fly status failed: %w", err)
	}

	// TODO: Parse JSON output for detailed status fields
	running := strings.Contains(out, "running")

	return &Status{
		Running:  running,
		Version:  f.cfg.ImageTag,
		Channel:  f.cfg.Channel,
		Domain:   f.cfg.Domain,
		Provider: "fly",
		Healthy:  running,
	}, nil
}

func (f *FlyProvider) Logs(follow bool) (io.ReadCloser, error) {
	// TODO: Stream fly logs output via exec.Command pipe
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", f.Name())
}

func (f *FlyProvider) Backup() (*BackupResult, error) {
	// TODO: Run fly postgres backup create and capture result
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", f.Name())
}

func (f *FlyProvider) Restore(backupID string) error {
	// TODO: Run fly postgres backup restore
	return fmt.Errorf("%s: not yet implemented — coming in a future release", f.Name())
}

func (f *FlyProvider) Rollback() error {
	// TODO: Look up previous release and run fly deploy --image with previous tag
	return fmt.Errorf("%s: not yet implemented — coming in a future release", f.Name())
}

func (f *FlyProvider) Destroy() error {
	cli := f.flyCLI()

	if _, err := runCommand(cli, "apps", "destroy", "--yes"); err != nil {
		return fmt.Errorf("fly apps destroy failed: %w", err)
	}
	return nil
}
