package tui

import (
	"fmt"
	"strings"
	"time"

	"github.com/charmbracelet/bubbles/spinner"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/registry"
	"github.com/jhandel/KMP/installer/internal/tui/components"
)

type updatePhase int

const (
	phaseCheckingUpdate updatePhase = iota
	phaseShowAvailable
	phaseConfirm
	phaseUpdating
	phaseUpdateDone
)

// updateCheckMsg carries the result of an update check.
type updateCheckMsg struct {
	current *config.Deployment
	release *registry.Release
	err     error
}

// updateDoneMsg signals the simulated update is complete.
type updateDoneMsg struct {
	err error
}

// updateTickMsg advances the simulated update progress.
type updateTickMsg struct{}

var updateSteps = []string{
	"Pulling new image...",
	"Stopping current containers...",
	"Running database migrations...",
	"Starting updated containers...",
	"Verifying health...",
}

// UpdateModel is the Bubble Tea model for the update screen.
type UpdateModel struct {
	phase      updatePhase
	spinner    spinner.Model
	current    *config.Deployment
	release    *registry.Release
	errorMsg   string
	updateStep int
	width      int
	height     int
}

// NewUpdateModel creates a new update screen model.
func NewUpdateModel() *UpdateModel {
	s := spinner.New()
	s.Spinner = spinner.Dot
	s.Style = lipgloss.NewStyle().Foreground(lipgloss.Color("#7D56F4"))

	return &UpdateModel{
		phase:   phaseCheckingUpdate,
		spinner: s,
	}
}

func (m *UpdateModel) Init() tea.Cmd {
	return tea.Batch(m.spinner.Tick, m.checkForUpdate)
}

func (m *UpdateModel) checkForUpdate() tea.Msg {
	cfg, err := config.Load()
	if err != nil {
		return updateCheckMsg{err: fmt.Errorf("failed to load config: %w", err)}
	}

	var deploy *config.Deployment
	for _, d := range cfg.Deployments {
		deploy = d
		break
	}

	if deploy == nil {
		deploy = &config.Deployment{
			Channel:  "release",
			ImageTag: "0.0.0",
		}
	}

	client := registry.NewClient()
	channel := deploy.Channel
	if channel == "" {
		channel = "release"
	}
	release, err := client.GetLatestByChannel(channel)
	if err != nil {
		// Return placeholder data if API unreachable
		return updateCheckMsg{
			current: deploy,
			release: &registry.Release{
				Name:    "v1.2.0",
				Tag:     "v1.2.0",
				Channel: channel,
				Body:    "- Improved performance\n- Bug fixes\n- New features",
			},
			err: fmt.Errorf("could not reach registry (showing placeholder): %w", err),
		}
	}

	return updateCheckMsg{current: deploy, release: release}
}

func (m *UpdateModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width = msg.Width
		m.height = msg.Height

	case tea.KeyMsg:
		return m.handleKeyMsg(msg)

	case spinner.TickMsg:
		var cmd tea.Cmd
		m.spinner, cmd = m.spinner.Update(msg)
		return m, cmd

	case updateCheckMsg:
		m.current = msg.current
		m.release = msg.release
		if msg.err != nil {
			m.errorMsg = msg.err.Error()
		}
		if msg.release != nil {
			m.phase = phaseShowAvailable
		} else {
			m.phase = phaseUpdateDone
		}
		return m, nil

	case updateTickMsg:
		m.updateStep++
		if m.updateStep >= len(updateSteps) {
			m.phase = phaseUpdateDone
			return m, nil
		}
		return m, m.tickUpdate()

	case updateDoneMsg:
		m.phase = phaseUpdateDone
		if msg.err != nil {
			m.errorMsg = msg.err.Error()
		}
		return m, nil
	}

	return m, nil
}

func (m *UpdateModel) handleKeyMsg(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	key := msg.String()

	if key == "ctrl+c" {
		return m, tea.Quit
	}

	switch m.phase {
	case phaseCheckingUpdate:
		// No input during check
	case phaseShowAvailable:
		if key == "enter" || key == "y" {
			m.phase = phaseConfirm
		} else if key == "q" || key == "esc" || key == "n" {
			return m, tea.Quit
		}
	case phaseConfirm:
		if key == "y" || key == "enter" {
			m.phase = phaseUpdating
			m.updateStep = 0
			return m, tea.Batch(m.spinner.Tick, m.tickUpdate())
		} else if key == "n" || key == "esc" {
			m.phase = phaseShowAvailable
		}
	case phaseUpdating:
		// No input during update
	case phaseUpdateDone:
		if key == "q" || key == "enter" || key == "esc" {
			return m, tea.Quit
		}
	}

	return m, nil
}

func (m *UpdateModel) tickUpdate() tea.Cmd {
	return tea.Tick(800*time.Millisecond, func(time.Time) tea.Msg {
		return updateTickMsg{}
	})
}

func (m *UpdateModel) View() string {
	var s strings.Builder

	s.WriteString(components.TitleStyle.Render("🏰 KMP Update"))
	s.WriteString("\n\n")

	if m.errorMsg != "" && m.phase != phaseUpdating && m.phase != phaseUpdateDone {
		s.WriteString(components.WarningStyle.Render("  ⚠ "+m.errorMsg) + "\n\n")
	}

	switch m.phase {
	case phaseCheckingUpdate:
		s.WriteString(m.viewChecking())
	case phaseShowAvailable:
		s.WriteString(m.viewAvailable())
	case phaseConfirm:
		s.WriteString(m.viewConfirm())
	case phaseUpdating:
		s.WriteString(m.viewUpdating())
	case phaseUpdateDone:
		s.WriteString(m.viewDone())
	}

	s.WriteString("\n\n")
	s.WriteString(m.renderFooter())

	return s.String()
}

func (m *UpdateModel) renderFooter() string {
	switch m.phase {
	case phaseCheckingUpdate, phaseUpdating:
		return components.SubtleStyle.Render("  Please wait...")
	case phaseShowAvailable:
		return components.SubtleStyle.Render("  enter: update • q: quit")
	case phaseConfirm:
		return components.SubtleStyle.Render("  y: confirm • n: cancel")
	default:
		return components.SubtleStyle.Render("  q: quit")
	}
}

func (m *UpdateModel) viewChecking() string {
	return components.BoxStyle.Render(
		"  " + m.spinner.View() + " Checking for updates...",
	)
}

func (m *UpdateModel) viewAvailable() string {
	var s strings.Builder

	currentTag := "unknown"
	if m.current != nil && m.current.ImageTag != "" {
		currentTag = m.current.ImageTag
	}

	s.WriteString(fmt.Sprintf("  Current version:   %s\n", currentTag))
	if m.release != nil {
		s.WriteString(fmt.Sprintf("  Available version: %s\n",
			components.SuccessStyle.Render(m.release.Tag)))
		s.WriteString(fmt.Sprintf("  Channel:           %s\n", m.release.Channel))

		if m.release.Body != "" {
			s.WriteString("\n  Changelog:\n")
			for _, line := range strings.Split(m.release.Body, "\n") {
				s.WriteString("    " + line + "\n")
			}
		}
	}

	return components.BoxStyle.Render(s.String())
}

func (m *UpdateModel) viewConfirm() string {
	tag := "latest"
	if m.release != nil {
		tag = m.release.Tag
	}
	return components.BoxStyle.Render(
		fmt.Sprintf("  Apply update to %s?\n\n  Press y to confirm, n to cancel.", tag),
	)
}

func (m *UpdateModel) viewUpdating() string {
	var s strings.Builder
	s.WriteString("  Updating KMP...\n\n")

	for i, step := range updateSteps {
		if i < m.updateStep {
			s.WriteString(components.SuccessStyle.Render("  ✓ "+step) + "\n")
		} else if i == m.updateStep {
			s.WriteString("  " + m.spinner.View() + " " + step + "\n")
		} else {
			s.WriteString(components.SubtleStyle.Render("  ○ "+step) + "\n")
		}
	}

	return components.BoxStyle.Render(s.String())
}

func (m *UpdateModel) viewDone() string {
	if m.errorMsg != "" && m.phase == phaseUpdateDone {
		return components.BoxStyle.Render(
			components.ErrorStyle.Render("  ✗ Update failed: "+m.errorMsg) +
				"\n\n  Run 'kmp rollback' to revert to the previous version.",
		)
	}

	tag := "latest"
	if m.release != nil {
		tag = m.release.Tag
	}

	return components.BoxStyle.Render(
		components.SuccessStyle.Render(fmt.Sprintf("  ✅ KMP updated to %s successfully!", tag)) +
			"\n\n  Run 'kmp status' to verify the deployment.",
	)
}
