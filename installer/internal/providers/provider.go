package providers

import "io"

// Provider defines the interface all deployment targets must implement.
type Provider interface {
	// Name returns the human-readable provider name
	Name() string

	// Detect returns true if this provider can be used in the current environment
	Detect() bool

	// Prerequisites returns what's needed before deployment
	Prerequisites() []Prerequisite

	// Install performs first-time deployment
	Install(cfg *DeployConfig) error

	// Update pulls and deploys a new version
	Update(version string) error

	// Status returns current deployment health and info
	Status() (*Status, error)

	// Logs returns application log output
	Logs(follow bool) (io.ReadCloser, error)

	// Backup creates a backup
	Backup() (*BackupResult, error)

	// Restore restores from a backup
	Restore(backupID string) error

	// Rollback reverts to the previous version
	Rollback() error

	// Destroy tears down the entire deployment
	Destroy() error
}

// Prerequisite describes something needed before deployment
type Prerequisite struct {
	Name        string
	Description string
	Met         bool
	InstallHint string
}

// DeployConfig holds configuration for a deployment
type DeployConfig struct {
	Name          string
	Provider      string
	Channel       string // release, beta, dev, nightly
	Domain        string
	Image         string // ghcr.io/jhandel/kmp
	ImageTag      string
	DatabaseDSN   string // BYO database, empty = bundled
	MySQLSSL      bool   // require SSL for external MySQL connections
	LocalDBType   string // "mariadb" or "postgres" when bundled; empty = mariadb default
	StorageType   string // local, s3, azure
	StorageConfig map[string]string
	CacheEngine   string // "apcu" (default) or "redis"
	RedisURL      string // remote redis:// URL; empty = bundled local Redis when CacheEngine=redis
	ComposeDir    string // where to store docker-compose files
	BackupConfig  BackupConfig
}

// BackupConfig holds backup configuration
type BackupConfig struct {
	Enabled       bool
	Schedule      string // cron expression
	RetentionDays int
	StorageType   string // local, s3, azure
	StorageConfig map[string]string
	EncryptionKey string
}

// Status holds current deployment status
type Status struct {
	Running        bool
	Version        string
	ImageTag       string
	Channel        string
	Domain         string
	Provider       string
	Healthy        bool
	DBConnected    bool
	CacheOK        bool
	Uptime         string
	LastBackup     string
	LastUpdate     string
	UpdaterRunning bool // true if kmp-updater sidecar is reachable
}

// BackupResult holds the result of a backup operation
type BackupResult struct {
	ID        string
	Timestamp string
	Size      int64
	Location  string
}
