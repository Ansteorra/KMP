package providers

import (
	"fmt"
	"io"

	"github.com/jhandel/KMP/installer/internal/config"
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
	// TODO: railway init to create project
	// TODO: railway add to add MySQL service
	// TODO: railway variables set for environment config
	// TODO: railway up to deploy the KMP image
	return fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Update(version string) error {
	// TODO: Update image reference and run railway up
	return fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Status() (*Status, error) {
	// TODO: Run railway status and parse output, plus HTTP health check
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
}

func (r *RailwayProvider) Logs(follow bool) (io.ReadCloser, error) {
	// TODO: Stream railway logs output
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", r.Name())
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
