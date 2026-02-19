package providers

import (
	"fmt"
	"io"

	"github.com/jhandel/KMP/installer/internal/config"
)

// VPSProvider deploys KMP to a remote server via SSH + Docker Compose.
type VPSProvider struct {
	cfg *config.Deployment
}

// NewVPSProvider creates a new VPS (SSH) provider.
func NewVPSProvider(cfg *config.Deployment) *VPSProvider {
	return &VPSProvider{cfg: cfg}
}

func (v *VPSProvider) Name() string { return "Cloud VM (VPS)" }

// Detect always returns true — SSH is universally available.
func (v *VPSProvider) Detect() bool {
	return true
}

func (v *VPSProvider) Prerequisites() []Prerequisite {
	sshInstalled := commandExists("ssh")

	return []Prerequisite{
		{
			Name:        "SSH client",
			Description: "ssh must be available",
			Met:         sshInstalled,
			InstallHint: "SSH is included with most operating systems. On Windows, enable OpenSSH or install Git Bash.",
		},
		{
			Name:        "SSH access",
			Description: "SSH key-based access to the target host must be configured",
			Met:         false, // Cannot auto-detect; user must confirm
			InstallHint: "Run: ssh-copy-id user@your-server to set up key-based access",
		},
	}
}

func (v *VPSProvider) Install(cfg *DeployConfig) error {
	// TODO: SSH to host and check if Docker is installed; install if needed
	// TODO: Upload docker-compose.yml and .env to the remote host
	// TODO: Run docker compose up -d on the remote host
	return fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Update(version string) error {
	// TODO: SSH exec: docker compose pull && docker compose up -d
	return fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Status() (*Status, error) {
	// TODO: SSH exec: docker compose ps + HTTP health check
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Logs(follow bool) (io.ReadCloser, error) {
	// TODO: SSH exec: docker compose logs [-f]
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Backup() (*BackupResult, error) {
	// TODO: SSH exec: run backup script (mysqldump + upload)
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Restore(backupID string) error {
	// TODO: SSH exec: download backup and restore via mysql
	return fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Rollback() error {
	// TODO: SSH exec: pull previous image tag and redeploy
	return fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}

func (v *VPSProvider) Destroy() error {
	// TODO: SSH exec: docker compose down -v
	return fmt.Errorf("%s: not yet implemented — coming in a future release", v.Name())
}
