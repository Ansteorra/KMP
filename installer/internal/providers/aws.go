package providers

import (
	"fmt"
	"io"

	"github.com/jhandel/KMP/installer/internal/config"
)

// AWSProvider deploys KMP to AWS using ECS Fargate + RDS MySQL + S3.
type AWSProvider struct {
	cfg *config.Deployment
}

// NewAWSProvider creates a new AWS provider.
func NewAWSProvider(cfg *config.Deployment) *AWSProvider {
	return &AWSProvider{cfg: cfg}
}

func (a *AWSProvider) Name() string { return "AWS" }

func (a *AWSProvider) Detect() bool {
	return commandExists("aws")
}

func (a *AWSProvider) Prerequisites() []Prerequisite {
	cliInstalled := commandExists("aws")

	authenticated := false
	if cliInstalled {
		_, err := runCommand("aws", "sts", "get-caller-identity")
		authenticated = err == nil
	}

	return []Prerequisite{
		{
			Name:        "AWS CLI",
			Description: "aws CLI v2 must be installed",
			Met:         cliInstalled,
			InstallHint: "Install: https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html",
		},
		{
			Name:        "AWS authentication",
			Description: "Must have valid AWS credentials configured",
			Met:         authenticated,
			InstallHint: "Run: aws configure",
		},
	}
}

func (a *AWSProvider) Install(cfg *DeployConfig) error {
	// TODO: Create ECS cluster
	// TODO: Create task definition with KMP image
	// TODO: Create RDS MySQL instance
	// TODO: Create S3 bucket for storage/backups
	// TODO: Create ECS service with load balancer
	// TODO: Configure security groups, VPC, and secrets
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Update(version string) error {
	// TODO: Register new task definition with updated image tag
	// TODO: Update ECS service to use new task definition
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Status() (*Status, error) {
	// TODO: aws ecs describe-services + HTTP health check
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Logs(follow bool) (io.ReadCloser, error) {
	// TODO: aws logs get-log-events from CloudWatch
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Backup() (*BackupResult, error) {
	// TODO: aws rds create-db-snapshot
	return nil, fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Restore(backupID string) error {
	// TODO: aws rds restore-db-instance-from-db-snapshot
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Rollback() error {
	// TODO: Retrieve previous task definition and update service
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}

func (a *AWSProvider) Destroy() error {
	// TODO: Delete ECS service, cluster, task definitions
	// TODO: Delete RDS instance (with final snapshot)
	// TODO: Delete S3 bucket and load balancer
	return fmt.Errorf("%s: not yet implemented — coming in a future release", a.Name())
}
