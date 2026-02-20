package config

import (
	"os"
	"path/filepath"

	"gopkg.in/yaml.v3"
)

// Config represents the KMP CLI configuration file
type Config struct {
	Version     int                    `yaml:"version"`
	Deployments map[string]*Deployment `yaml:"deployments"`
}

// Deployment represents a single KMP deployment
type Deployment struct {
	Provider        string            `yaml:"provider"`
	Channel         string            `yaml:"channel"`
	Domain          string            `yaml:"domain"`
	Image           string            `yaml:"image"`
	ImageTag        string            `yaml:"image_tag"`
	ComposeDir      string            `yaml:"compose_dir,omitempty"`
	DatabaseDSN     string            `yaml:"database_dsn,omitempty"`
	LocalDBType     string            `yaml:"local_db_type,omitempty"` // "mariadb" or "postgres"
	StorageType     string            `yaml:"storage_type"`
	StorageConfig   map[string]string `yaml:"storage_config,omitempty"`
	CacheEngine     string            `yaml:"cache_engine,omitempty"` // "apcu" or "redis"
	RedisURL        string            `yaml:"redis_url,omitempty"`    // empty = bundled local Redis
	BackupEnabled   bool              `yaml:"backup_enabled"`
	BackupSchedule  string            `yaml:"backup_schedule,omitempty"`
	BackupRetention int               `yaml:"backup_retention_days,omitempty"`
}

// DefaultConfigDir returns ~/.kmp
func DefaultConfigDir() string {
	home, _ := os.UserHomeDir()
	return filepath.Join(home, ".kmp")
}

// ConfigPath returns the path to the config file
func ConfigPath() string {
	return filepath.Join(DefaultConfigDir(), "config.yaml")
}

// Load reads the config file, returns empty config if not found
func Load() (*Config, error) {
	cfg := &Config{
		Version:     1,
		Deployments: make(map[string]*Deployment),
	}

	data, err := os.ReadFile(ConfigPath())
	if err != nil {
		if os.IsNotExist(err) {
			return cfg, nil
		}
		return nil, err
	}

	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, err
	}
	return cfg, nil
}

// Save writes the config file
func (c *Config) Save() error {
	dir := DefaultConfigDir()
	if err := os.MkdirAll(dir, 0700); err != nil {
		return err
	}

	data, err := yaml.Marshal(c)
	if err != nil {
		return err
	}

	return os.WriteFile(ConfigPath(), data, 0600)
}
