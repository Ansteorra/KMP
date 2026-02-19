package providers

import (
	"fmt"

	"github.com/jhandel/KMP/installer/internal/config"
)

// ProviderInfo describes an available provider.
type ProviderInfo struct {
	ID             string
	Name           string
	Description    string
	RequiresDocker bool
	Constructor    func(*config.Deployment) Provider
}

// AvailableProviders returns all known providers.
func AvailableProviders() []ProviderInfo {
	return []ProviderInfo{
		{"docker", "This machine (Docker)", "Run KMP locally using Docker Compose + Caddy", true,
			func(d *config.Deployment) Provider { return NewDockerProvider(d) }},
		{"fly", "Fly.io", "Fly Machines + Fly Postgres", false,
			func(d *config.Deployment) Provider { return NewFlyProvider(d) }},
		{"railway", "Railway", "Railway containers + managed MySQL", false,
			func(d *config.Deployment) Provider { return NewRailwayProvider(d) }},
		{"azure", "Azure", "Azure Container Apps + Azure Database", false,
			func(d *config.Deployment) Provider { return NewAzureProvider(d) }},
		{"aws", "AWS", "ECS Fargate + RDS MySQL + S3", false,
			func(d *config.Deployment) Provider { return NewAWSProvider(d) }},
		{"vps", "Cloud VM (VPS)", "Deploy to a remote server via SSH", false,
			func(d *config.Deployment) Provider { return NewVPSProvider(d) }},
	}
}

// GetProvider returns a provider by ID.
func GetProvider(id string, deployment *config.Deployment) (Provider, error) {
	for _, p := range AvailableProviders() {
		if p.ID == id {
			return p.Constructor(deployment), nil
		}
	}
	return nil, fmt.Errorf("unknown provider: %s", id)
}
