package tui

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/charmbracelet/bubbles/spinner"
	"github.com/charmbracelet/bubbles/textinput"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
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

var dbChoices = []string{"Bundled MariaDB (recommended)", "Existing MySQL server", "Existing PostgreSQL server"}
var dbValues = []string{"bundled", "mysql", "postgres"}

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
	err error
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

	return &InstallModel{
		step:        stepWelcome,
		domainInput: ti,
		spinner:     s,
		prereqs: []prereqCheck{
			{"Docker", "checking", ""},
			{"Docker Compose", "checking", ""},
			{"Port 80", "checking", ""},
			{"Port 443", "checking", ""},
		},
	}
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

	return m, nil
}

func (m *InstallModel) handleKeyMsg(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	key := msg.String()

	switch m.step {
	case stepWelcome:
		if key == "enter" {
			m.step = stepProvider
			m.cursor = 0
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
				m.domain = "localhost"
			}
			m.step = stepChannel
			m.cursor = 0
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
			m.cursor = 0
		case "esc":
			m.step = stepDomain
			m.domainInput.Focus()
			return m, textinput.Blink
		}

	case stepDatabase:
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
			m.step = stepProgress
			m.progressStep = 0
			return m, tea.Batch(m.spinner.Tick, m.runInstall())
		case "esc":
			m.step = stepChannel
			m.cursor = m.channel
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
	dbType := dbValues[m.database]

	return func() tea.Msg {
		// Map channel name to the actual Docker image tag.
		imageTag := channel
		if channel == "release" {
			imageTag = "latest"
		}

		cfg := &providers.DeployConfig{
			Name:        "default",
			Provider:    providerID,
			Channel:     channel,
			Domain:      domain,
			Image:       "ghcr.io/jhandel/kmp",
			ImageTag:    imageTag,
			StorageType: "local",
			StorageConfig: map[string]string{},
			BackupConfig: providers.BackupConfig{
				Enabled:       true,
				Schedule:      "0 3 * * *",
				RetentionDays: 30,
			},
		}
		if dbType != "bundled" {
			cfg.DatabaseDSN = "" // will be filled in future BYO-DSN step
		}

		var provider providers.Provider
		switch providerID {
		case "docker":
			provider = providers.NewDockerProvider(nil)
		default:
			return installDoneMsg{err: fmt.Errorf("provider %q not yet supported — use Docker", providerID)}
		}

		if err := provider.Install(cfg); err != nil {
			return installDoneMsg{err: err}
		}
		return installDoneMsg{}
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
		"Channel", "Database", "Deploying", "Complete",
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
	case stepProgress:
		return components.SubtleStyle.Render("Please wait...")
	case stepComplete:
		return components.SubtleStyle.Render("enter/q: exit")
	default:
		return components.SubtleStyle.Render("↑/↓: navigate • enter: select • esc: back")
	}
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
	s.WriteString(components.SubtleStyle.Render("  Leave blank and press Enter for localhost (development mode)."))

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

	return components.BoxStyle.Render(s.String())
}

func (m *InstallModel) viewProgress() string {
	var s strings.Builder
	s.WriteString("  Deploying KMP...\n\n")

	// Summary of selections
	s.WriteString(components.SubtleStyle.Render(fmt.Sprintf(
		"  Provider: %s  |  Domain: %s  |  Channel: %s  |  Database: %s\n\n",
		providerChoices[m.provider].name, m.domain,
		channelValues[m.channel], dbValues[m.database],
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

	if m.errorMsg != "" {
		result := fmt.Sprintf(`
  ❌ Installation failed

  Error: %s

  Troubleshooting:
    • Check Docker is running: docker info
    • Check container logs:
        docker compose -f %s/docker-compose.yml logs app
    • Check all logs:
        docker compose -f %s/docker-compose.yml logs
    • See docs: https://github.com/jhandel/KMP/docs/deployment/

  Press Enter or q to exit.
`, m.errorMsg, deployDir, deployDir)
		return components.BoxStyle.Render(lipgloss.NewStyle().Foreground(lipgloss.Color("#FF5555")).Render(result))
	}

	domain := m.domain
	scheme := "https"
	if domain == "localhost" {
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
		dbValues[m.database],
		scheme, domain,
		deployDir)

	return components.BoxStyle.Render(components.SuccessStyle.Render(result))
}
