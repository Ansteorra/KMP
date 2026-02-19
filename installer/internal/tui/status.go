package tui

import (
	"fmt"
	"strings"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/health"
	"github.com/jhandel/KMP/installer/internal/tui/components"
)

// statusFetchedMsg carries the result of a health check.
type statusFetchedMsg struct {
	health *health.Response
	deploy *config.Deployment
	err    error
}

// StatusModel is the Bubble Tea model for the status screen.
type StatusModel struct {
	health   *health.Response
	deploy   *config.Deployment
	loading  bool
	errorMsg string
	width    int
	height   int
}

// NewStatusModel creates a new status display model.
func NewStatusModel() *StatusModel {
	return &StatusModel{
		loading: true,
	}
}

func (m *StatusModel) Init() tea.Cmd {
	return m.fetchStatus
}

func (m *StatusModel) fetchStatus() tea.Msg {
	cfg, err := config.Load()
	if err != nil {
		return statusFetchedMsg{err: fmt.Errorf("failed to load config: %w", err)}
	}

	// Use the first deployment found, or return placeholder data
	var deploy *config.Deployment
	for _, d := range cfg.Deployments {
		deploy = d
		break
	}

	if deploy == nil {
		// No deployment found — return placeholder
		return statusFetchedMsg{
			deploy: &config.Deployment{
				Provider: "docker",
				Channel:  "release",
				Domain:   "localhost",
			},
			health: nil,
			err:    fmt.Errorf("no deployment configured — showing placeholder data"),
		}
	}

	scheme := "https"
	if deploy.Domain == "localhost" {
		scheme = "http"
	}
	url := fmt.Sprintf("%s://%s", scheme, deploy.Domain)

	h, err := health.Check(url)
	if err != nil {
		return statusFetchedMsg{deploy: deploy, health: nil, err: err}
	}

	return statusFetchedMsg{deploy: deploy, health: h, err: nil}
}

func (m *StatusModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width = msg.Width
		m.height = msg.Height

	case tea.KeyMsg:
		switch msg.String() {
		case "q", "ctrl+c", "esc":
			return m, tea.Quit
		case "r":
			m.loading = true
			m.errorMsg = ""
			return m, m.fetchStatus
		}

	case statusFetchedMsg:
		m.loading = false
		m.deploy = msg.deploy
		m.health = msg.health
		if msg.err != nil {
			m.errorMsg = msg.err.Error()
		}
	}

	return m, nil
}

func (m *StatusModel) View() string {
	var s strings.Builder

	s.WriteString(components.TitleStyle.Render("🏰 KMP Status"))
	s.WriteString("\n\n")

	if m.loading {
		s.WriteString(components.InfoStyle.Render("  Fetching deployment status..."))
		s.WriteString("\n\n")
		s.WriteString(components.SubtleStyle.Render("  q: quit • r: refresh"))
		return s.String()
	}

	if m.errorMsg != "" {
		s.WriteString(components.WarningStyle.Render("  ⚠ "+m.errorMsg))
		s.WriteString("\n\n")
	}

	// Deployment info
	if m.deploy != nil {
		s.WriteString(m.renderDeploymentInfo())
		s.WriteString("\n")
	}

	// Health info
	s.WriteString(m.renderHealthInfo())
	s.WriteString("\n\n")

	s.WriteString(components.SubtleStyle.Render("  q: quit • r: refresh"))

	return s.String()
}

func (m *StatusModel) renderDeploymentInfo() string {
	d := m.deploy
	if d == nil {
		return ""
	}

	rows := []string{
		fmt.Sprintf("  Provider:  %s", valueOrPlaceholder(d.Provider)),
		fmt.Sprintf("  Domain:    %s", valueOrPlaceholder(d.Domain)),
		fmt.Sprintf("  Channel:   %s", valueOrPlaceholder(d.Channel)),
		fmt.Sprintf("  Image:     %s", valueOrPlaceholder(d.Image)),
		fmt.Sprintf("  Tag:       %s", valueOrPlaceholder(d.ImageTag)),
	}

	content := strings.Join(rows, "\n")
	return components.BoxStyle.Render("  Deployment\n\n" + content)
}

func (m *StatusModel) renderHealthInfo() string {
	if m.health == nil {
		return components.BoxStyle.Render(
			"  Health\n\n" +
				statusLine("Status", "Unknown", false) +
				statusLine("Database", "Unknown", false) +
				statusLine("Cache", "Unknown", false) +
				fmt.Sprintf("  Version:   %s\n", components.SubtleStyle.Render("n/a")) +
				fmt.Sprintf("  Uptime:    %s\n", components.SubtleStyle.Render("n/a")) +
				fmt.Sprintf("  Checked:   %s\n", components.SubtleStyle.Render(time.Now().Format("15:04:05"))),
		)
	}

	h := m.health
	return components.BoxStyle.Render(
		"  Health\n\n" +
			statusLine("Status", h.Status, h.IsHealthy()) +
			statusLine("Database", boolStatus(h.DB), h.DB) +
			statusLine("Cache", boolStatus(h.Cache), h.Cache) +
			fmt.Sprintf("  Version:   %s\n", h.Version) +
			fmt.Sprintf("  Checked:   %s\n", h.Timestamp),
	)
}

func statusLine(label, value string, ok bool) string {
	icon := components.ErrorStyle.Render("✗")
	styled := components.ErrorStyle.Render(value)
	if ok {
		icon = components.SuccessStyle.Render("✓")
		styled = components.SuccessStyle.Render(value)
	}
	return fmt.Sprintf("  %s %-10s %s\n", icon, label+":", styled)
}

func boolStatus(b bool) string {
	if b {
		return "Connected"
	}
	return "Disconnected"
}

func valueOrPlaceholder(v string) string {
	if v == "" {
		return components.SubtleStyle.Render("not set")
	}
	return v
}
