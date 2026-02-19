package components

import "github.com/charmbracelet/lipgloss"

var (
	// TitleStyle for headers
	TitleStyle = lipgloss.NewStyle().
			Bold(true).
			Foreground(lipgloss.Color("#FAFAFA")).
			Background(lipgloss.Color("#7D56F4")).
			Padding(0, 1)

	// SuccessStyle for positive messages
	SuccessStyle = lipgloss.NewStyle().
			Foreground(lipgloss.Color("#04B575"))

	// ErrorStyle for errors
	ErrorStyle = lipgloss.NewStyle().
			Foreground(lipgloss.Color("#FF4672"))

	// WarningStyle for warnings
	WarningStyle = lipgloss.NewStyle().
			Foreground(lipgloss.Color("#FFA500"))

	// InfoStyle for informational text
	InfoStyle = lipgloss.NewStyle().
			Foreground(lipgloss.Color("#7D56F4"))

	// SubtleStyle for secondary text
	SubtleStyle = lipgloss.NewStyle().
			Foreground(lipgloss.Color("#626262"))

	// BoxStyle for bordered content
	BoxStyle = lipgloss.NewStyle().
			Border(lipgloss.RoundedBorder()).
			BorderForeground(lipgloss.Color("#7D56F4")).
			Padding(1, 2)
)
