package tui

import (
	"fmt"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/charmbracelet/bubbles/spinner"
	"github.com/charmbracelet/bubbles/textinput"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/providers"
	"github.com/jhandel/KMP/installer/internal/tui/components"
)

type installStep int

const (
	stepWelcome installStep = iota
	stepProvider
	stepPrereqs
	stepDomain
	stepChannel
	stepDatabase
	stepEmail
	stepStorage
	stepCache
	stepProgress
	stepComplete
)

type providerChoice struct {
	name           string
	description    string
	id             string
	requiresDocker bool
}

var providerChoices = []providerChoice{
	{"This machine (Docker)", "Run KMP locally using Docker Compose + Caddy", "docker", true},
	{"Cloud VM (VPS)", "Deploy to a remote server via SSH", "vps", false},
	{"Azure", "Azure Container Apps + Azure Database", "azure", false},
	{"AWS", "ECS Fargate + RDS MySQL + S3", "aws", false},
	{"Fly.io", "Fly Machines + Fly Postgres", "fly", false},
	{"Railway", "Railway containers + managed MySQL", "railway", false},
}

var channelChoices = []string{"Release (stable, recommended)", "Beta", "Dev", "Nightly"}
var channelValues = []string{"release", "beta", "dev", "nightly"}

var defaultDBChoices = []string{
	"Bundled MariaDB (recommended)",
	"Bundled PostgreSQL",
	"Existing MySQL server",
	"Existing PostgreSQL server",
}
var defaultDBValues = []string{"bundled-mariadb", "bundled-postgres", "mysql", "postgres"}

var railwayDBChoices = []string{
	"Railway managed MySQL (provision automatically)",
	"Existing MySQL server",
	"Existing PostgreSQL server",
}
var railwayDBValues = []string{"railway-managed-mysql", "mysql", "postgres"}

var defaultCacheChoices = []string{
	"APCu (local in-process cache, recommended for single container)",
	"Redis (bundled — add a local Redis container)",
	"Redis (external — provide a remote Redis URL)",
}
var defaultCacheValues = []string{"apcu", "redis-local", "redis-remote"}

var railwayCacheChoices = []string{
	"APCu (in-process cache)",
	"Railway managed Redis (provision automatically)",
	"Existing Redis URL",
}
var railwayCacheValues = []string{"apcu", "railway-managed-redis", "redis-remote"}

type prereqCheck struct {
	name   string
	status string // "checking", "pass", "fail"
	detail string
}

// progressTickMsg drives the simulated progress steps.
type progressTickMsg struct{}

// prereqDoneMsg signals prerequisite checks are complete.
type prereqDoneMsg struct {
	results []prereqCheck
}

// installDoneMsg signals the background install completed.
type installDoneMsg struct {
	err    error
	domain string
}

var progressSteps = []string{
	"Pulling KMP image...",
	"Starting database...",
	"Running migrations...",
	"Starting application...",
	"Configuring reverse proxy...",
	"Running health checks...",
}

// InstallModel is the Bubble Tea model for the install wizard.
type InstallModel struct {
	step   installStep
	cursor int

	// Selections
	provider int
	channel  int
	database int
	domain   string

	// Sub-models
	domainInput textinput.Model
	spinner     spinner.Model

	// Prerequisites
	prereqs []prereqCheck

	// Database external connection
	dbSubStep    int               // 0=choice, 1=conn method, 2=DSN, 3=DB name, 4=parts form, 5=SSL
	dbConnMethod int               // 0=dsn, 1=parts
	dsnInput     textinput.Model   // connection string field
	dbNameInput  textinput.Model   // prompted when DSN lacks a database name
	dbInputs     []textinput.Model // [host, port, dbname, user, password]
	dbFocusIdx   int
	mysqlSSL     bool // require SSL for external MySQL

	// Email configuration
	emailChoice  int // 0=skip, 1=smtp
	emailSubStep int // 0=choice, 1=smtp form
	smtpInputs   []textinput.Model
	smtpFocusIdx int

	// Storage configuration
	storageChoice   int // 0=local, 1=s3, 2=azure
	storageSubStep  int // 0=choice, 1=s3/azure form
	storageInputs   []textinput.Model
	storageFocusIdx int

	// Cache configuration
	cacheChoice  int // 0=apcu, 1=redis-local, 2=redis-remote
	cacheSubStep int // 0=choice, 1=remote URL form
	redisInput   textinput.Model

	// Progress
	progressStep int
	progressDone bool
	errorMsg     string

	// Window size
	width  int
	height int
}

// NewInstallModel creates a new install wizard model.
func NewInstallModel() *InstallModel {
	ti := textinput.New()
	ti.Placeholder = "kingdom.example.com (or press Enter for localhost)"
	ti.Focus()
	ti.Width = 50

	s := spinner.New()
	s.Spinner = spinner.Dot
	s.Style = lipgloss.NewStyle().Foreground(lipgloss.Color("#7D56F4"))

	ri := textinput.New()
	ri.Placeholder = "redis://[:password@]host:6379"
	ri.Width = 50

	di := textinput.New()
	di.Placeholder = "mysql://user:password@host:3306/dbname  or  postgres://user:password@host:5432/dbname"
	di.Width = 70

	dni := textinput.New()
	dni.Placeholder = "kmp"
	dni.Width = 30

	m := &InstallModel{
		step:          stepWelcome,
		domainInput:   ti,
		spinner:       s,
		redisInput:    ri,
		dsnInput:      di,
		dbNameInput:   dni,
		smtpInputs:    newSmtpInputs(),
		storageInputs: newS3Inputs(),
		prereqs: []prereqCheck{
			{"Docker", "checking", ""},
			{"Docker Compose", "checking", ""},
			{"Port 80", "checking", ""},
			{"Port 443", "checking", ""},
		},
	}
	m.loadDefaults()
	return m
}

func (m *InstallModel) selectedProviderID() string {
	if m.provider < 0 || m.provider >= len(providerChoices) {
		return providerChoices[0].id
	}

	return providerChoices[m.provider].id
}

func dbOptionsForProvider(providerID string) ([]string, []string) {
	if providerID == "railway" {
		return railwayDBChoices, railwayDBValues
	}

	return defaultDBChoices, defaultDBValues
}

func cacheOptionsForProvider(providerID string) ([]string, []string) {
	if providerID == "railway" {
		return railwayCacheChoices, railwayCacheValues
	}

	return defaultCacheChoices, defaultCacheValues
}

func selectedValue(values []string, idx int) string {
	if idx >= 0 && idx < len(values) {
		return values[idx]
	}
	if len(values) == 0 {
		return ""
	}
	return values[0]
}

func selectedLabel(labels []string, idx int) string {
	if idx >= 0 && idx < len(labels) {
		return labels[idx]
	}
	if len(labels) == 0 {
		return ""
	}
	return labels[0]
}

// loadDefaults pre-fills the model from a previously saved deployment config.
func (m *InstallModel) loadDefaults() {
	cfg, err := config.Load()
	if err != nil || len(cfg.Deployments) == 0 {
		return
	}
	dep, ok := cfg.Deployments["default"]
	if !ok {
		return
	}

	// Seed cursors so each step opens with the previous selection highlighted
	m.cursor = 0

	// Provider
	for i, p := range providerChoices {
		if p.id == dep.Provider {
			m.provider = i
			break
		}
	}

	// Channel
	for i, c := range channelValues {
		if c == dep.Channel {
			m.channel = i
			break
		}
	}

	// Domain
	if dep.Domain != "" {
		m.domain = dep.Domain
		m.domainInput.SetValue(dep.Domain)
	}

	// Database type + pre-fill connection form
	if dep.Provider == "railway" {
		switch {
		case dep.DatabaseDSN == "":
			m.database = 0 // railway-managed mysql
		case strings.HasPrefix(dep.DatabaseDSN, "postgres"):
			m.database = 2 // external postgres
		default:
			m.database = 1 // external mysql
		}
	} else {
		switch {
		case dep.DatabaseDSN == "" && dep.LocalDBType == "postgres":
			m.database = 1 // bundled postgres
		case dep.DatabaseDSN == "":
			m.database = 0 // bundled mariadb
		case strings.HasPrefix(dep.DatabaseDSN, "postgres"):
			m.database = 3 // external postgres
		default:
			m.database = 2 // external mysql
		}
	}
	if dep.DatabaseDSN != "" {
		m.dbConnMethod = 0 // DSN method since we have a saved string
		m.dsnInput.SetValue(dep.DatabaseDSN)
	}
	m.mysqlSSL = dep.MySQLSSL

	// Email / SMTP
	if smtpHost := dep.StorageConfig["smtp_host"]; smtpHost != "" {
		m.emailChoice = 1
		m.smtpInputs = newSmtpInputs()
		m.smtpInputs[0].SetValue(smtpHost)
		m.smtpInputs[1].SetValue(dep.StorageConfig["smtp_port"])
		m.smtpInputs[2].SetValue(dep.StorageConfig["email_from"])
		m.smtpInputs[3].SetValue(dep.StorageConfig["smtp_user"])
		m.smtpInputs[4].SetValue(dep.StorageConfig["smtp_pass"])
	}

	// Storage
	switch dep.StorageType {
	case "s3":
		m.storageChoice = 1
		m.storageInputs = newS3Inputs()
		m.storageInputs[0].SetValue(dep.StorageConfig["s3_bucket"])
		m.storageInputs[1].SetValue(dep.StorageConfig["s3_region"])
		m.storageInputs[2].SetValue(dep.StorageConfig["s3_key"])
		m.storageInputs[3].SetValue(dep.StorageConfig["s3_secret"])
		m.storageInputs[4].SetValue(dep.StorageConfig["s3_endpoint"])
	case "azure":
		m.storageChoice = 2
		m.storageInputs = newAzureInputs()
		m.storageInputs[0].SetValue(dep.StorageConfig["azure_connection_string"])
		if len(m.storageInputs) > 1 {
			m.storageInputs[1].SetValue(dep.StorageConfig["azure_container"])
		}
	}

	// Cache
	if dep.Provider == "railway" {
		switch {
		case dep.CacheEngine == "redis" && dep.RedisURL != "":
			m.cacheChoice = 2 // existing redis url
			m.redisInput.SetValue(dep.RedisURL)
		case dep.CacheEngine == "redis":
			m.cacheChoice = 1 // railway managed redis
		default:
			m.cacheChoice = 0 // apcu
		}
	} else {
		switch {
		case dep.CacheEngine == "redis" && dep.RedisURL != "":
			m.cacheChoice = 2
			m.redisInput.SetValue(dep.RedisURL)
		case dep.CacheEngine == "redis":
			m.cacheChoice = 1
		default:
			m.cacheChoice = 0
		}
	}
}

func newSmtpInputs() []textinput.Model {
	specs := []struct {
		placeholder string
		echoPass    bool
	}{
		{"smtp.gmail.com", false},
		{"587", false},
		{"noreply@mykingdom.org", false},
		{"user@example.com (optional)", false},
		{"app password (optional)", true},
	}
	inputs := make([]textinput.Model, len(specs))
	for i, s := range specs {
		t := textinput.New()
		t.Placeholder = s.placeholder
		t.Width = 44
		if s.echoPass {
			t.EchoMode = textinput.EchoPassword
		}
		inputs[i] = t
	}
	inputs[0].Focus()
	return inputs
}

func newS3Inputs() []textinput.Model {
	specs := []struct{ placeholder string }{
		{"my-kmp-documents"},
		{"us-east-1"},
		{"leave blank to use IAM role"},
		{"leave blank to use IAM role"},
		{"optional — for MinIO / DigitalOcean Spaces etc."},
	}
	inputs := make([]textinput.Model, len(specs))
	for i, s := range specs {
		t := textinput.New()
		t.Placeholder = s.placeholder
		t.Width = 44
		if i == 3 {
			t.EchoMode = textinput.EchoPassword
		}
		inputs[i] = t
	}
	inputs[0].Focus()
	return inputs
}

func newAzureInputs() []textinput.Model {
	specs := []struct {
		placeholder string
		label       string
	}{
		{"DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;EndpointSuffix=core.windows.net", "connection string"},
		{"documents", "container name"},
	}
	inputs := make([]textinput.Model, len(specs))
	for i, s := range specs {
		t := textinput.New()
		t.Placeholder = s.placeholder
		t.Width = 60
		inputs[i] = t
	}
	inputs[0].Focus()
	return inputs
}

func newDBInputs(dbType string) []textinput.Model {
	defaultPort := "3306"
	if dbType == "postgres" {
		defaultPort = "5432"
	}
	specs := []struct {
		placeholder string
		echoPass    bool
	}{
		{"db.example.com", false},
		{defaultPort, false},
		{"kmp", false},
		{"kmpuser", false},
		{"password", true},
	}
	inputs := make([]textinput.Model, len(specs))
	for i, s := range specs {
		t := textinput.New()
		t.Placeholder = s.placeholder
		t.Width = 44
		if s.echoPass {
			t.EchoMode = textinput.EchoPassword
		}
		inputs[i] = t
	}
	inputs[0].Focus()
	return inputs
}

// dsnHasDatabase returns true when the DSN URL contains a non-empty database name.
func dsnHasDatabase(dsn string) bool {
	u, err := url.Parse(dsn)
	if err != nil {
		return false
	}
	db := strings.TrimPrefix(u.Path, "/")
	// Remove query string from path tail (e.g. some DSNs embed ?sslmode=require in path)
	if idx := strings.Index(db, "?"); idx >= 0 {
		db = db[:idx]
	}
	return db != ""
}

// dbComplete transitions from DB config to either the SSL prompt (MySQL) or email step.
func (m *InstallModel) dbComplete() {
	_, dbValues := dbOptionsForProvider(m.selectedProviderID())
	if selectedValue(dbValues, m.database) == "mysql" {
		m.dbSubStep = 5
		if m.mysqlSSL {
			m.cursor = 0 // Yes
		} else {
			m.cursor = 1 // No
		}
		return
	}
	m.step = stepEmail
	m.cursor = 0
	m.emailChoice = 0
	m.emailSubStep = 0
}

// advanceFormFocus moves focus to the next input in a slice.
// Returns true if we reached the end (caller should advance the step).
func advanceFormFocus(inputs []textinput.Model, idx *int, delta int) bool {
	inputs[*idx].Blur()
	next := *idx + delta
	if next < 0 {
		next = 0
	}
	if next >= len(inputs) {
		return true // reached end
	}
	*idx = next
	inputs[*idx].Focus()
	return false
}

func (m *InstallModel) Init() tea.Cmd {
	return nil
}

func (m *InstallModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width = msg.Width
		m.height = msg.Height
		return m, nil

	case tea.KeyMsg:
		// Global quit
		if msg.String() == "ctrl+c" {
			return m, tea.Quit
		}
		return m.handleKeyMsg(msg)

	case spinner.TickMsg:
		var cmd tea.Cmd
		m.spinner, cmd = m.spinner.Update(msg)
		return m, cmd

	case progressTickMsg:
		return m.handleProgressTick()

	case prereqDoneMsg:
		m.prereqs = msg.results
		return m, nil

	case installDoneMsg:
		return m.handleInstallDone(msg)
	}

	// Pass through to text input when on domain step
	if m.step == stepDomain {
		var cmd tea.Cmd
		m.domainInput, cmd = m.domainInput.Update(msg)
		return m, cmd
	}

	// Pass through to DB DSN / name / parts inputs when in external DB form
	if m.step == stepDatabase && m.dbSubStep == 2 {
		var cmd tea.Cmd
		m.dsnInput, cmd = m.dsnInput.Update(msg)
		return m, cmd
	}
	if m.step == stepDatabase && m.dbSubStep == 3 {
		var cmd tea.Cmd
		m.dbNameInput, cmd = m.dbNameInput.Update(msg)
		return m, cmd
	}
	if m.step == stepDatabase && m.dbSubStep == 4 && m.dbFocusIdx < len(m.dbInputs) {
		var cmd tea.Cmd
		m.dbInputs[m.dbFocusIdx], cmd = m.dbInputs[m.dbFocusIdx].Update(msg)
		return m, cmd
	}

	// Pass through to SMTP inputs when in email form sub-step
	if m.step == stepEmail && m.emailSubStep == 1 && m.smtpFocusIdx < len(m.smtpInputs) {
		var cmd tea.Cmd
		m.smtpInputs[m.smtpFocusIdx], cmd = m.smtpInputs[m.smtpFocusIdx].Update(msg)
		return m, cmd
	}

	// Pass through to storage inputs when in storage form sub-step
	if m.step == stepStorage && m.storageSubStep == 1 && m.storageFocusIdx < len(m.storageInputs) {
		var cmd tea.Cmd
		m.storageInputs[m.storageFocusIdx], cmd = m.storageInputs[m.storageFocusIdx].Update(msg)
		return m, cmd
	}

	return m, nil
}

func (m *InstallModel) handleKeyMsg(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	key := msg.String()

	switch m.step {
	case stepWelcome:
		if key == "enter" {
			m.step = stepProvider
			m.cursor = m.provider
		} else if key == "q" || key == "esc" {
			return m, tea.Quit
		}

	case stepProvider:
		switch key {
		case "up", "k":
			if m.cursor > 0 {
				m.cursor--
			}
		case "down", "j":
			if m.cursor < len(providerChoices)-1 {
				m.cursor++
			}
		case "enter":
			m.provider = m.cursor
			m.step = stepPrereqs
			m.cursor = 0
			return m, m.runPrereqChecks()
		case "esc":
			m.step = stepWelcome
		}

	case stepPrereqs:
		if key == "enter" {
			m.step = stepDomain
			m.domainInput.Focus()
			return m, textinput.Blink
		} else if key == "esc" {
			m.step = stepProvider
			m.cursor = m.provider
		}

	case stepDomain:
		if key == "enter" {
			m.domain = m.domainInput.Value()
			if m.domain == "" {
				if m.selectedProviderID() == "railway" {
					m.domain = ""
				} else {
					m.domain = "localhost"
				}
			}
			m.step = stepChannel
			m.cursor = m.channel
			return m, nil
		} else if key == "esc" {
			m.step = stepPrereqs
			return m, nil
		}
		// Text input handles other keys via the Update pass-through
		var cmd tea.Cmd
		m.domainInput, cmd = m.domainInput.Update(msg)
		return m, cmd

	case stepChannel:
		switch key {
		case "up", "k":
			if m.cursor > 0 {
				m.cursor--
			}
		case "down", "j":
			if m.cursor < len(channelChoices)-1 {
				m.cursor++
			}
		case "enter":
			m.channel = m.cursor
			m.step = stepDatabase
			m.cursor = m.database
		case "esc":
			m.step = stepDomain
			m.domainInput.Focus()
			return m, textinput.Blink
		}

	case stepDatabase:
		_, dbValues := dbOptionsForProvider(m.selectedProviderID())
		dbValue := selectedValue(dbValues, m.database)
		switch m.dbSubStep {
		case 0:
			// DB type choice
			dbChoices, _ := dbOptionsForProvider(m.selectedProviderID())
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < len(dbChoices)-1 {
					m.cursor++
				}
			case "enter":
				m.database = m.cursor
				dbValue = selectedValue(dbValues, m.database)
				if strings.HasPrefix(dbValue, "bundled") || dbValue == "railway-managed-mysql" {
					m.step = stepEmail
					m.cursor = 0
					m.emailChoice = 0
					m.emailSubStep = 0
				} else {
					// External — ask how they want to provide connection details
					m.dbSubStep = 1
					m.cursor = m.dbConnMethod
				}
			case "esc":
				m.step = stepChannel
				m.cursor = m.channel
			}

		case 1:
			// Connection method choice: DSN string or individual parts
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < 1 {
					m.cursor++
				}
			case "enter":
				m.dbConnMethod = m.cursor
				if m.dbConnMethod == 0 {
					// DSN string path
					m.dbSubStep = 2
					if dbValue == "postgres" {
						m.dsnInput.Placeholder = "postgres://user:password@host:5432/dbname"
					} else {
						m.dsnInput.Placeholder = "mysql://user:password@host:3306/dbname"
					}
					m.dsnInput.Focus()
				} else {
					// Individual parts path
					m.dbSubStep = 4
					m.dbFocusIdx = 0
					m.dbInputs = newDBInputs(dbValue)
				}
			case "esc":
				m.dbSubStep = 0
				m.cursor = m.database
			}

		case 2:
			// DSN string input
			switch key {
			case "enter", "tab":
				dsn := m.dsnInput.Value()
				if dsn == "" {
					return m, nil
				}
				if !dsnHasDatabase(dsn) {
					m.dbSubStep = 3
					m.dbNameInput.Reset()
					m.dbNameInput.Focus()
				} else {
					m.dbComplete()
				}
				return m, nil
			case "esc":
				m.dbSubStep = 1
				m.cursor = m.dbConnMethod
				return m, nil
			}
			var cmd tea.Cmd
			m.dsnInput, cmd = m.dsnInput.Update(msg)
			return m, cmd

		case 3:
			// DB name (DSN was missing it)
			switch key {
			case "enter", "tab":
				m.dbComplete()
				return m, nil
			case "esc":
				m.dbSubStep = 2
				m.dsnInput.Focus()
				return m, nil
			}
			var cmd tea.Cmd
			m.dbNameInput, cmd = m.dbNameInput.Update(msg)
			return m, cmd

		case 4:
			// Individual parts form
			switch key {
			case "tab", "down":
				if done := advanceFormFocus(m.dbInputs, &m.dbFocusIdx, 1); done {
					m.dbComplete()
				}
				return m, nil
			case "shift+tab", "up":
				if m.dbFocusIdx == 0 {
					m.dbSubStep = 1
					m.cursor = m.dbConnMethod
				} else {
					advanceFormFocus(m.dbInputs, &m.dbFocusIdx, -1) //nolint:errcheck
				}
				return m, nil
			case "enter":
				if done := advanceFormFocus(m.dbInputs, &m.dbFocusIdx, 1); done {
					m.dbComplete()
				}
				return m, nil
			case "esc":
				m.dbSubStep = 1
				m.cursor = m.dbConnMethod
				return m, nil
			}
			var cmd tea.Cmd
			m.dbInputs[m.dbFocusIdx], cmd = m.dbInputs[m.dbFocusIdx].Update(msg)
			return m, cmd

		case 5:
			// MySQL SSL toggle
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < 1 {
					m.cursor++
				}
			case "enter":
				m.mysqlSSL = m.cursor == 0 // 0=Yes, 1=No
				m.step = stepEmail
				m.cursor = 0
				m.emailChoice = 0
				m.emailSubStep = 0
			case "esc":
				// Go back to connection method choice
				m.dbSubStep = 1
				m.cursor = m.dbConnMethod
			}
		}

	case stepEmail:
		if m.emailSubStep == 0 {
			// Choice: skip or SMTP
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < 1 {
					m.cursor++
				}
			case "enter":
				m.emailChoice = m.cursor
				if m.emailChoice == 0 {
					// Skip — go straight to storage
					m.step = stepStorage
					m.cursor = 0
					m.storageChoice = 0
					m.storageSubStep = 0
				} else {
					// SMTP — show form
					m.emailSubStep = 1
					m.smtpFocusIdx = 0
					m.smtpInputs = newSmtpInputs()
					m.smtpInputs[0].Focus()
				}
			case "esc":
				m.step = stepDatabase
				m.cursor = m.database
			}
		} else {
			// SMTP form sub-step — key events handled via Update pass-through
			// but navigation keys are intercepted here first
			switch key {
			case "tab", "down":
				if done := advanceFormFocus(m.smtpInputs, &m.smtpFocusIdx, 1); done {
					m.step = stepStorage
					m.cursor = 0
					m.storageChoice = 0
					m.storageSubStep = 0
				}
				return m, nil
			case "shift+tab", "up":
				if m.smtpFocusIdx == 0 {
					m.emailSubStep = 0
					m.cursor = m.emailChoice
				} else {
					advanceFormFocus(m.smtpInputs, &m.smtpFocusIdx, -1) //nolint:errcheck
				}
				return m, nil
			case "enter":
				// Advance field or submit if on last
				if done := advanceFormFocus(m.smtpInputs, &m.smtpFocusIdx, 1); done {
					m.step = stepStorage
					m.cursor = 0
					m.storageChoice = 0
					m.storageSubStep = 0
				}
				return m, nil
			case "esc":
				m.emailSubStep = 0
				m.cursor = m.emailChoice
				return m, nil
			}
			// Pass remaining key events to the active text input
			var cmd tea.Cmd
			m.smtpInputs[m.smtpFocusIdx], cmd = m.smtpInputs[m.smtpFocusIdx].Update(msg)
			return m, cmd
		}

	case stepStorage:
		if m.storageSubStep == 0 {
			// Storage type choice
			var storageChoices = []string{"local", "s3", "azure"}
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < len(storageChoices)-1 {
					m.cursor++
				}
			case "enter":
				m.storageChoice = m.cursor
				if m.storageChoice == 0 {
					// Local — go to cache step
					m.step = stepCache
					m.cursor = m.cacheChoice
					m.cacheSubStep = 0
					return m, nil
				}
				// S3 or Azure — show form
				m.storageSubStep = 1
				m.storageFocusIdx = 0
				if m.storageChoice == 1 {
					m.storageInputs = newS3Inputs()
				} else {
					m.storageInputs = newAzureInputs()
				}
				m.storageInputs[0].Focus()
			case "esc":
				m.step = stepEmail
				m.cursor = m.emailChoice
				m.emailSubStep = 0
			}
		} else {
			// Storage form sub-step
			switch key {
			case "tab", "down":
				if done := advanceFormFocus(m.storageInputs, &m.storageFocusIdx, 1); done {
					m.step = stepCache
					m.cursor = m.cacheChoice
					m.cacheSubStep = 0
					return m, nil
				}
				return m, nil
			case "shift+tab", "up":
				if m.storageFocusIdx == 0 {
					m.storageSubStep = 0
					m.cursor = m.storageChoice
				} else {
					advanceFormFocus(m.storageInputs, &m.storageFocusIdx, -1) //nolint:errcheck
				}
				return m, nil
			case "enter":
				if done := advanceFormFocus(m.storageInputs, &m.storageFocusIdx, 1); done {
					m.step = stepCache
					m.cursor = m.cacheChoice
					m.cacheSubStep = 0
					return m, nil
				}
				return m, nil
			case "esc":
				m.storageSubStep = 0
				m.cursor = m.storageChoice
				return m, nil
			}
			var cmd tea.Cmd
			m.storageInputs[m.storageFocusIdx], cmd = m.storageInputs[m.storageFocusIdx].Update(msg)
			return m, cmd
		}

	case stepCache:
		cacheChoices, cacheValues := cacheOptionsForProvider(m.selectedProviderID())
		if m.cacheSubStep == 0 {
			switch key {
			case "up", "k":
				if m.cursor > 0 {
					m.cursor--
				}
			case "down", "j":
				if m.cursor < len(cacheChoices)-1 {
					m.cursor++
				}
			case "enter":
				m.cacheChoice = m.cursor
				if selectedValue(cacheValues, m.cacheChoice) == "redis-remote" {
					m.cacheSubStep = 1
					m.redisInput.Focus()
				} else {
					m.step = stepProgress
					m.progressStep = 0
					return m, tea.Batch(m.spinner.Tick, m.runInstall())
				}
			case "esc":
				m.step = stepStorage
				m.cursor = m.storageChoice
				m.storageSubStep = 0
			}
		} else {
			// Redis URL input
			switch key {
			case "enter", "tab":
				m.step = stepProgress
				m.progressStep = 0
				return m, tea.Batch(m.spinner.Tick, m.runInstall())
			case "esc":
				m.cacheSubStep = 0
				m.cursor = m.cacheChoice
				return m, nil
			}
			var cmd tea.Cmd
			m.redisInput, cmd = m.redisInput.Update(msg)
			return m, cmd
		}

	case stepProgress:
		// No input during progress
		return m, nil

	case stepComplete:
		if key == "enter" || key == "q" || key == "esc" {
			return m, tea.Quit
		}
	}

	return m, nil
}

func (m *InstallModel) handleInstallDone(msg installDoneMsg) (tea.Model, tea.Cmd) {
	if msg.err != nil {
		m.errorMsg = msg.err.Error()
		m.progressDone = true
		m.step = stepComplete
		return m, nil
	}
	if strings.TrimSpace(msg.domain) != "" {
		m.domain = strings.TrimSpace(msg.domain)
	}
	m.progressStep = len(progressSteps)
	m.progressDone = true
	m.step = stepComplete
	return m, nil
}

func (m *InstallModel) handleProgressTick() (tea.Model, tea.Cmd) {
	m.progressStep++
	if m.progressStep >= len(progressSteps) {
		m.progressDone = true
		m.step = stepComplete
		return m, nil
	}
	return m, nil
}

func (m *InstallModel) runInstall() tea.Cmd {
	// Capture selections to pass into goroutine.
	providerID := providerChoices[m.provider].id
	channel := channelValues[m.channel]
	domain := m.domain
	_, dbValues := dbOptionsForProvider(providerID)
	dbValue := selectedValue(dbValues, m.database)

	// Build LocalDBType for bundled selections
	var localDBType string
	switch dbValue {
	case "bundled-postgres":
		localDBType = "postgres"
	case "bundled-mariadb":
		localDBType = "mariadb"
	}

	// Build DSN from external DB (DSN string or individual parts)
	var externalDSN string
	if dbValue == "mysql" || dbValue == "postgres" {
		if m.dbConnMethod == 0 {
			// DSN string path
			externalDSN = m.dsnInput.Value()
			if dbName := m.dbNameInput.Value(); dbName != "" && externalDSN != "" {
				if u, err := url.Parse(externalDSN); err == nil && strings.TrimPrefix(u.Path, "/") == "" {
					u.Path = "/" + dbName
					externalDSN = u.String()
				}
			}
		} else if len(m.dbInputs) == 5 {
			// Individual parts path — build DSN from fields
			host := m.dbInputs[0].Value()
			port := m.dbInputs[1].Value()
			dbName := m.dbInputs[2].Value()
			user := m.dbInputs[3].Value()
			pass := m.dbInputs[4].Value()
			if host == "" {
				host = m.dbInputs[0].Placeholder
			}
			if port == "" {
				port = m.dbInputs[1].Placeholder
			}
			if dbName == "" {
				dbName = m.dbInputs[2].Placeholder
			}
			if user == "" {
				user = m.dbInputs[3].Placeholder
			}
			scheme := "mysql"
			if dbValue == "postgres" {
				scheme = "postgres"
			}
			externalDSN = fmt.Sprintf("%s://%s:%s@%s:%s/%s", scheme, user, pass, host, port, dbName)
		}
	}

	// Capture email config
	smtpEnabled := m.emailChoice == 1
	var smtpVals [5]string
	for i := range m.smtpInputs {
		if i < len(m.smtpInputs) {
			smtpVals[i] = m.smtpInputs[i].Value()
		}
	}

	// Capture storage config
	storageChoiceIdx := m.storageChoice
	var storageVals []string
	for _, inp := range m.storageInputs {
		storageVals = append(storageVals, inp.Value())
	}

	// Capture cache config
	_, cacheValues := cacheOptionsForProvider(providerID)
	cacheValue := selectedValue(cacheValues, m.cacheChoice)
	cacheEngine := "apcu"
	redisURL := ""
	if cacheValue == "redis-local" || cacheValue == "railway-managed-redis" {
		cacheEngine = "redis"
	} else if cacheValue == "redis-remote" {
		cacheEngine = "redis"
		redisURL = m.redisInput.Value()
	}

	return func() tea.Msg {
		// Map channel name to the actual Docker image tag.
		imageTag := channel
		if channel == "release" {
			imageTag = "latest"
		}

		storageConfig := map[string]string{}
		storageType := "local"
		if storageChoiceIdx == 1 {
			storageType = "s3"
			// S3: bucket, region, key, secret, endpoint
			keys := []string{"s3_bucket", "s3_region", "s3_key", "s3_secret", "s3_endpoint"}
			for i, k := range keys {
				if i < len(storageVals) {
					storageConfig[k] = storageVals[i]
				}
			}
		} else if storageChoiceIdx == 2 {
			storageType = "azure"
			if len(storageVals) > 0 {
				storageConfig["azure_connection_string"] = storageVals[0]
			}
			if len(storageVals) > 1 && storageVals[1] != "" {
				storageConfig["azure_container"] = storageVals[1]
			} else {
				storageConfig["azure_container"] = "documents"
			}
		}

		if smtpEnabled {
			storageConfig["smtp_host"] = smtpVals[0]
			storageConfig["smtp_port"] = smtpVals[1]
			storageConfig["email_from"] = smtpVals[2]
			storageConfig["smtp_user"] = smtpVals[3]
			storageConfig["smtp_pass"] = smtpVals[4]
		}

		cfg := &providers.DeployConfig{
			Name:          "default",
			Provider:      providerID,
			Channel:       channel,
			Domain:        domain,
			Image:         "ghcr.io/jhandel/kmp",
			ImageTag:      imageTag,
			StorageType:   storageType,
			StorageConfig: storageConfig,
			DatabaseDSN:   externalDSN,
			MySQLSSL:      m.mysqlSSL,
			LocalDBType:   localDBType,
			CacheEngine:   cacheEngine,
			RedisURL:      redisURL,
			BackupConfig: providers.BackupConfig{
				Enabled:       true,
				Schedule:      "0 3 * * *",
				RetentionDays: 30,
			},
		}

		var provider providers.Provider
		switch providerID {
		case "docker":
			provider = providers.NewDockerProvider(nil)
		case "railway":
			provider = providers.NewRailwayProvider(nil)
		default:
			return installDoneMsg{err: fmt.Errorf("provider %q not yet supported — use Docker", providerID)}
		}

		if err := provider.Install(cfg); err != nil {
			return installDoneMsg{err: err}
		}
		return installDoneMsg{domain: cfg.Domain}
	}
}

func (m *InstallModel) runPrereqChecks() tea.Cmd {
	providerID := providerChoices[m.provider].id
	return func() tea.Msg {
		var results []prereqCheck

		switch providerID {
		case "docker":
			// Check Docker
			out, err := exec.Command("docker", "version", "--format", "{{.Server.Version}}").Output()
			if err == nil {
				results = append(results, prereqCheck{"Docker", "pass", "v" + strings.TrimSpace(string(out))})
			} else {
				results = append(results, prereqCheck{"Docker", "fail", "not found — install from https://docs.docker.com/engine/install/"})
			}

			// Check Docker Compose
			out, err = exec.Command("docker", "compose", "version", "--short").Output()
			if err == nil {
				results = append(results, prereqCheck{"Docker Compose", "pass", "v" + strings.TrimSpace(string(out))})
			} else {
				results = append(results, prereqCheck{"Docker Compose", "fail", "not found — included with Docker Desktop"})
			}

			// Check ports (we use the provider for this)
			p := providers.NewDockerProvider(nil)
			for _, prereq := range p.Prerequisites() {
				if prereq.Name == "Port 80 available" || prereq.Name == "Port 443 available" {
					status := "pass"
					detail := "Available"
					if !prereq.Met {
						status = "fail"
						detail = prereq.InstallHint
					}
					results = append(results, prereqCheck{prereq.Name, status, detail})
				}
			}

		case "railway":
			p := providers.NewRailwayProvider(nil)
			for _, prereq := range p.Prerequisites() {
				status := "pass"
				detail := prereq.Description
				if detail == "" {
					detail = "Available"
				}
				if !prereq.Met {
					status = "fail"
					detail = prereq.InstallHint
				}
				results = append(results, prereqCheck{prereq.Name, status, detail})
			}

		default:
			results = append(results, prereqCheck{
				"Provider support", "fail",
				fmt.Sprintf("%s provider coming soon — please select Docker for now", providerID),
			})
		}

		return prereqDoneMsg{results: results}
	}
}

func (m *InstallModel) View() string {
	var s strings.Builder

	header := m.renderHeader()
	s.WriteString(header)
	s.WriteString("\n\n")

	switch m.step {
	case stepWelcome:
		s.WriteString(m.viewWelcome())
	case stepProvider:
		s.WriteString(m.viewProvider())
	case stepPrereqs:
		s.WriteString(m.viewPrereqs())
	case stepDomain:
		s.WriteString(m.viewDomain())
	case stepChannel:
		s.WriteString(m.viewChannel())
	case stepDatabase:
		s.WriteString(m.viewDatabase())
	case stepEmail:
		s.WriteString(m.viewEmail())
	case stepStorage:
		s.WriteString(m.viewStorage())
	case stepCache:
		s.WriteString(m.viewCache())
	case stepProgress:
		s.WriteString(m.viewProgress())
	case stepComplete:
		s.WriteString(m.viewComplete())
	}

	s.WriteString("\n\n")
	s.WriteString(m.renderFooter())

	return s.String()
}

func (m *InstallModel) renderHeader() string {
	stepNames := []string{
		"Welcome", "Provider", "Prerequisites", "Domain",
		"Channel", "Database", "Email", "Storage", "Cache", "Deploying", "Complete",
	}

	var dots strings.Builder
	for i, name := range stepNames {
		if i == int(m.step) {
			dots.WriteString(components.InfoStyle.Render("● " + name))
		} else if i < int(m.step) {
			dots.WriteString(components.SuccessStyle.Render("✓ " + name))
		} else {
			dots.WriteString(components.SubtleStyle.Render("○ " + name))
		}
		if i < len(stepNames)-1 {
			dots.WriteString(components.SubtleStyle.Render(" → "))
		}
	}

	title := components.TitleStyle.Render("🏰 KMP Installer")
	return title + "\n" + dots.String()
}

func (m *InstallModel) renderFooter() string {
	switch m.step {
	case stepWelcome:
		return components.SubtleStyle.Render("enter: continue • q: quit")
	case stepDatabase:
		if m.dbSubStep == 2 || m.dbSubStep == 3 {
			return components.SubtleStyle.Render("enter/tab: confirm • esc: back")
		}
		if m.dbSubStep == 4 {
			return components.SubtleStyle.Render("tab/enter: next field • shift+tab/up: prev • esc: back")
		}
	case stepEmail:
		if m.emailSubStep == 1 {
			return components.SubtleStyle.Render("tab/enter: next field • shift+tab/up: prev • esc: back")
		}
	case stepStorage:
		if m.storageSubStep == 1 {
			return components.SubtleStyle.Render("tab/enter: next field • shift+tab/up: prev • esc: back")
		}
	case stepCache:
		if m.cacheSubStep == 1 {
			return components.SubtleStyle.Render("enter/tab: confirm • esc: back")
		}
	case stepProgress:
		return components.SubtleStyle.Render("Please wait...")
	case stepComplete:
		return components.SubtleStyle.Render("enter/q: exit")
	}
	return components.SubtleStyle.Render("↑/↓: navigate • enter: select • esc: back")
}

func (m *InstallModel) viewWelcome() string {
	logo := `
    ╔═══════════════════════════════════════════╗
    ║                                           ║
    ║   🏰  Kingdom Management Portal           ║
    ║       Installation Wizard                 ║
    ║                                           ║
    ╚═══════════════════════════════════════════╝`

	welcome := components.BoxStyle.Render(logo + "\n\n" +
		"  Welcome to KMP! This wizard will guide you through\n" +
		"  deploying the Kingdom Management Portal.\n\n" +
		"  Press Enter to begin.")

	return welcome
}

func (m *InstallModel) viewProvider() string {
	var s strings.Builder
	s.WriteString("  Where would you like to deploy KMP?\n\n")

	for i, p := range providerChoices {
		cursor := "  ○ "
		style := lipgloss.NewStyle()
		if i == m.cursor {
			cursor = "  ● "
			style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
		}
		s.WriteString(style.Render(cursor+p.name) + "\n")
		if i == m.cursor {
			s.WriteString(components.SubtleStyle.Render("    "+p.description) + "\n")
		}
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewPrereqs() string {
	var s strings.Builder
	s.WriteString("  Checking prerequisites for " + providerChoices[m.provider].name + "...\n\n")

	for _, p := range m.prereqs {
		switch p.status {
		case "pass":
			s.WriteString(components.SuccessStyle.Render("  ✓ "+p.name) + " ")
			s.WriteString(components.SubtleStyle.Render(p.detail) + "\n")
		case "fail":
			s.WriteString(components.ErrorStyle.Render("  ✗ "+p.name) + " ")
			s.WriteString(components.ErrorStyle.Render(p.detail) + "\n")
		default:
			s.WriteString(components.WarningStyle.Render("  ⋯ "+p.name) + " ")
			s.WriteString(components.SubtleStyle.Render("checking...") + "\n")
		}
	}

	allPass := true
	for _, p := range m.prereqs {
		if p.status != "pass" {
			allPass = false
			break
		}
	}

	if allPass {
		s.WriteString("\n" + components.SuccessStyle.Render("  All checks passed! Press Enter to continue."))
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewDomain() string {
	var s strings.Builder
	s.WriteString("  Enter your domain name:\n\n")
	s.WriteString("  " + m.domainInput.View() + "\n\n")
	if m.selectedProviderID() == "railway" {
		s.WriteString(components.SubtleStyle.Render("  Leave blank to auto-generate a Railway domain and use it for APP_FULL_BASE_URL."))
	} else {
		s.WriteString(components.SubtleStyle.Render("  Leave blank and press Enter for localhost (development mode)."))
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewChannel() string {
	var s strings.Builder
	s.WriteString("  Select the release channel:\n\n")

	for i, ch := range channelChoices {
		cursor := "  ○ "
		style := lipgloss.NewStyle()
		if i == m.cursor {
			cursor = "  ● "
			style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
		}
		s.WriteString(style.Render(cursor+ch) + "\n")
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewDatabase() string {
	var s strings.Builder

	dbChoices, dbValues := dbOptionsForProvider(m.selectedProviderID())
	dbValue := selectedValue(dbValues, m.database)
	dbLabel := "MySQL"
	if dbValue == "postgres" {
		dbLabel = "PostgreSQL"
	}

	switch m.dbSubStep {
	case 0:
		s.WriteString(fmt.Sprintf("  Provider: %s\n\n", providerChoices[m.provider].name))
		s.WriteString("  Select database configuration:\n\n")
		for i, db := range dbChoices {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+db) + "\n")
		}

	case 1:
		s.WriteString(fmt.Sprintf("  How would you like to provide %s connection details?\n\n", dbLabel))
		connMethodLabels := []string{
			"Paste a connection string (DSN URL)",
			"Enter individual details (host, port, user, etc.)",
		}
		for i, label := range connMethodLabels {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+label) + "\n")
		}
		s.WriteString("\n" + components.SubtleStyle.Render("  Tip: Most cloud services (Neon, PlanetScale, Railway, Supabase)\n  offer a one-click copy of the connection string."))

	case 2:
		s.WriteString(fmt.Sprintf("  %s Connection String\n\n", dbLabel))
		s.WriteString("  Paste your connection string below:\n\n")
		s.WriteString("  " + m.dsnInput.View() + "\n\n")
		s.WriteString(components.SubtleStyle.Render("  Examples:\n"))
		if dbValue == "postgres" {
			s.WriteString(components.SubtleStyle.Render("    postgres://user:pass@ep-xxx.us-east-2.aws.neon.tech/dbname?sslmode=require\n"))
			s.WriteString(components.SubtleStyle.Render("    postgresql://user:pass@host:5432/dbname\n"))
		} else {
			s.WriteString(components.SubtleStyle.Render("    mysql://user:pass@host:3306/dbname\n"))
			s.WriteString(components.SubtleStyle.Render("    mysql://user:pass@monorail.proxy.rlwy.net:12345/railway\n"))
		}

	case 3:
		s.WriteString("  Database Name\n\n")
		s.WriteString(components.SubtleStyle.Render("  Your connection string doesn't include a database name.\n"))
		s.WriteString(components.SubtleStyle.Render("  Enter the database name to use:\n\n"))
		s.WriteString("  Database name:  " + m.dbNameInput.View() + "\n")

	case 4:
		s.WriteString(fmt.Sprintf("  %s Connection Details\n\n", dbLabel))
		labels := []string{"Host", "Port", "Database Name", "Username", "Password"}
		for i, label := range labels {
			focused := m.dbFocusIdx == i
			ls := lipgloss.NewStyle()
			if focused {
				ls = ls.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(ls.Render(fmt.Sprintf("  %-16s", label+":")) + " ")
			s.WriteString(m.dbInputs[i].View() + "\n")
		}
		s.WriteString("\n" + components.SubtleStyle.Render("  A DSN will be built from these values."))

	case 5:
		s.WriteString("  Require SSL for MySQL connection?\n\n")
		sslChoices := []string{"Yes — require SSL (recommended for cloud databases)", "No — connect without SSL"}
		for i, label := range sslChoices {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+label) + "\n")
		}
		s.WriteString("\n" + components.SubtleStyle.Render("  Azure MySQL, PlanetScale, and most cloud providers require SSL."))
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewEmail() string {
	var s strings.Builder

	if m.emailSubStep == 0 {
		s.WriteString("  Configure email delivery (optional):\n\n")
		choices := []string{
			"Skip — configure email after installation",
			"Configure SMTP (send notifications, password resets, etc.)",
		}
		for i, c := range choices {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+c) + "\n")
		}
	} else {
		// SMTP form
		s.WriteString("  SMTP Configuration\n\n")
		labels := []string{"Host", "Port", "From Address", "Username", "Password"}
		for i, label := range labels {
			focused := m.smtpFocusIdx == i
			prefix := "  "
			ls := lipgloss.NewStyle()
			if focused {
				ls = ls.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(ls.Render(fmt.Sprintf("%s%-14s", prefix, label+":")) + " ")
			s.WriteString(m.smtpInputs[i].View() + "\n")
		}
	}

	return components.BoxStyle.Render(s.String())
}

var storageTypeLabels = []string{
	"Local filesystem (default — files stored on the server)",
	"Amazon S3 / S3-compatible object storage",
	"Azure Blob Storage",
}

func (m *InstallModel) viewStorage() string {
	var s strings.Builder

	if m.storageSubStep == 0 {
		s.WriteString("  Configure document storage:\n\n")
		for i, label := range storageTypeLabels {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+label) + "\n")
		}
	} else {
		var labels []string
		if m.storageChoice == 1 {
			// S3
			s.WriteString("  Amazon S3 Configuration\n\n")
			labels = []string{"Bucket", "Region", "Access Key ID", "Secret Key", "Endpoint (optional)"}
		} else {
			// Azure — connection string + container
			s.WriteString("  Azure Blob Storage Configuration\n\n")
			labels = []string{"Connection String", "Container Name"}
		}
		for i, label := range labels {
			if i >= len(m.storageInputs) {
				break
			}
			focused := m.storageFocusIdx == i
			prefix := "  "
			ls := lipgloss.NewStyle()
			if focused {
				ls = ls.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(ls.Render(fmt.Sprintf("%s%-20s", prefix, label+":")) + " ")
			s.WriteString(m.storageInputs[i].View() + "\n")
		}
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewCache() string {
	var s strings.Builder
	cacheChoices, _ := cacheOptionsForProvider(m.selectedProviderID())

	if m.cacheSubStep == 0 {
		s.WriteString("  Configure caching:\n\n")
		for i, label := range cacheChoices {
			cursor := "  ○ "
			style := lipgloss.NewStyle()
			if i == m.cursor {
				cursor = "  ● "
				style = style.Bold(true).Foreground(lipgloss.Color("#7D56F4"))
			}
			s.WriteString(style.Render(cursor+label) + "\n")
		}
		s.WriteString("\n" + components.SubtleStyle.Render(
			"  APCu is fast and simple but is per-container only.\n  Redis is required for multi-replica (cloud) deployments.",
		))
	} else {
		s.WriteString("  Remote Redis Configuration\n\n")
		s.WriteString(components.SubtleStyle.Render("  URL format: redis://[:password@]host[:port]\n\n"))
		s.WriteString("  Redis URL:  " + m.redisInput.View() + "\n")
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewProgress() string {
	var s strings.Builder
	s.WriteString("  Deploying KMP...\n\n")

	// Summary of selections
	dbChoices, _ := dbOptionsForProvider(m.selectedProviderID())
	selectedDB := selectedLabel(dbChoices, m.database)
	displayDomain := m.domain
	if m.selectedProviderID() == "railway" && strings.TrimSpace(displayDomain) == "" {
		displayDomain = "auto (Railway default)"
	}
	s.WriteString(components.SubtleStyle.Render(fmt.Sprintf(
		"  Provider: %s  |  Domain: %s  |  Channel: %s  |  Database: %s\n\n",
		providerChoices[m.provider].name, displayDomain,
		channelValues[m.channel], selectedDB,
	)))

	for i, step := range progressSteps {
		if i < m.progressStep {
			s.WriteString(components.SuccessStyle.Render("  ✓ "+step) + "\n")
		} else if i == m.progressStep {
			s.WriteString("  " + m.spinner.View() + " " + step + "\n")
		} else {
			s.WriteString(components.SubtleStyle.Render("  ○ "+step) + "\n")
		}
	}

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewComplete() string {
	deployDir := filepath.Join(os.Getenv("HOME"), ".kmp", "deployments", "default")
	if cfg, err := config.Load(); err == nil {
		if dep, ok := cfg.Deployments["default"]; ok && strings.TrimSpace(dep.ComposeDir) != "" {
			deployDir = dep.ComposeDir
		}
	}
	providerID := m.selectedProviderID()
	dbChoices, _ := dbOptionsForProvider(providerID)
	selectedDB := selectedLabel(dbChoices, m.database)

	if m.errorMsg != "" {
		var troubleshooting string
		if providerID == "docker" {
			troubleshooting = fmt.Sprintf(`  Troubleshooting:
    • Check Docker is running: docker info
    • Check container logs:
        docker compose -f %s/docker-compose.yml logs app
    • Check all logs:
        docker compose -f %s/docker-compose.yml logs
    • See docs: https://github.com/jhandel/KMP/docs/deployment/
`, deployDir, deployDir)
		} else {
			troubleshooting = `  Troubleshooting:
    • Check Railway dashboard deploy/runtime logs
    • Run: kmp logs --follow
    • Verify Railway variables for selected managed/existing services
    • See docs: https://github.com/jhandel/KMP/docs/deployment/
`
		}
		result := fmt.Sprintf(`
  ❌ Installation failed

  Error: %s

%s

  Press Enter or q to exit.
`, m.errorMsg, troubleshooting)
		return components.BoxStyle.Render(lipgloss.NewStyle().Foreground(lipgloss.Color("#FF5555")).Render(result))
	}

	domain := m.domain
	scheme := "https"
	if domain == "" {
		domain = "localhost"
		scheme = "http"
	} else if domain == "localhost" {
		scheme = "http"
	}

	result := fmt.Sprintf(`
  ✅ KMP has been deployed successfully!

  ┌─────────────────────────────────────────┐
  │  URL:      %s://%s              
  │  Provider: %s                           
  │  Channel:  %s                           
  │  Database: %s                           
  └─────────────────────────────────────────┘

  Default login credentials:
    Email:    admin@test.com
    Password: Password123
    ⚠️  Change your password immediately after first login!

  Next steps:
    1. Open %s://%s in your browser
    2. Log in with the credentials above
    3. Go to Members → edit your profile → change password
    4. Run 'kmp status' to check health
    5. Run 'kmp backup' to create your first backup

  Deployment files: %s

`, scheme, domain,
		providerChoices[m.provider].name,
		channelValues[m.channel],
		selectedDB,
		scheme, domain,
		deployDir)

	return components.BoxStyle.Render(components.SuccessStyle.Render(result))
}
