package providers

import (
	"fmt"
	"io"

	"github.com/jhandel/KMP/installer/internal/config"
)

// AzureProvider deploys KMP to Azure Container Apps + Azure Database for MySQL.
type AzureProvider struct {
	cfg *config.Deployment
}

// NewAzureProvider creates a new Azure provider.
func NewAzureProvider(cfg *config.Deployment) *AzureProvider {
	return &AzureProvider{cfg: cfg}
}

func (a *AzureProvider) Name() string { return "Azure" }

func (a *AzureProvider) Detect() bool {
	return commandExists("az")
}

func (a *AzureProvider) Prerequisites() []Prerequisite {
	cliInstalled := commandExists("az")

	authenticated := false
	if cliInstalled {
		_, err := runCommand("az", "account", "show")
		authenticated = err == nil
	}

	return []Prerequisite{
		{
			Name:        "Azure CLI",
			Description: "az CLI must be installed",
			Met:         cliInstalled,
			InstallHint: "Install: https://learn.microsoft.com/en-us/cli/azure/install-azure-cli",
		},
		{
			Name:        "Azure authentication",
			Description: "Must be logged in to Azure",
			Met:         authenticated,
			InstallHint: "Run: az login",
		},
	}
}

func (a *AzureProvider) Install(cfg *DeployConfig) error {
	// TODO: az group create --name kmp-rg --location eastus
	// TODO: az containerapp env create
	// TODO: az mysql flexible-server create
	// TODO: az containerapp create --image ghcr.io/jhandel/kmp:TAG
	// TODO: Configure networking, secrets, and custom domain
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Update(version string) error {
	// TODO: az containerapp update --image ghcr.io/jhandel/kmp:VERSION
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Status() (*Status, error) {
	// TODO: az containerapp show + HTTP health check
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Logs(follow bool) (io.ReadCloser, error) {
	// TODO: az containerapp logs show --follow
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Backup() (*BackupResult, error) {
	// TODO: az mysql flexible-server backup create
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Restore(backupID string) error {
	// TODO: az mysql flexible-server backup restore
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Rollback() error {
	// TODO: Retrieve previous image tag and run Update
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AzureProvider) Destroy() error {
	// TODO: az group delete --name kmp-rg --yes --no-wait
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}
