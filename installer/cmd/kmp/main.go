package main

import (
	"bufio"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"strings"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/jhandel/KMP/installer/internal/config"
	"github.com/jhandel/KMP/installer/internal/providers"
	"github.com/jhandel/KMP/installer/internal/registry"
	"github.com/jhandel/KMP/installer/internal/selfupdate"
	"github.com/jhandel/KMP/installer/internal/tui"
	"github.com/spf13/cobra"
)

var version = "dev"

func main() {
	rootCmd := &cobra.Command{
		Use:   "kmp",
		Short: "KMP Manager — deploy and manage Kingdom Management Portal",
		Long:  "Standalone management tool for the Kingdom Management Portal (KMP).\nDeploy, update, monitor, and back up KMP installations.",
		PersistentPreRun: func(cmd *cobra.Command, args []string) {
			// Skip update check when running self-update itself
			if cmd.Name() != "self-update" {
				go selfupdate.CheckAndNotify(version)
			}
		},
	}

	rootCmd.AddCommand(
		newInstallCmd(),
		newUpdateCmd(),
		newStatusCmd(),
		newLogsCmd(),
		newBackupCmd(),
		newRestoreCmd(),
		newRollbackCmd(),
		newConfigCmd(),
		newSelfUpdateCmd(),
		newVersionCmd(),
	)

	if err := rootCmd.Execute(); err != nil {
		os.Exit(1)
	}
}

// loadDeployment loads the default deployment config and its provider.
func loadDeployment() (*config.Deployment, providers.Provider, error) {
	cfg, err := config.Load()
	if err != nil {
		return nil, nil, fmt.Errorf("failed to load config: %w", err)
	}

	// For now, use "default" deployment. Later: support multiple deployments via --name flag
	dep, ok := cfg.Deployments["default"]
	if !ok {
		return nil, nil, fmt.Errorf("no deployment found. Run `kmp install` first")
	}

	provider, err := providers.GetProvider(dep.Provider, dep)
	if err != nil {
		return nil, nil, err
	}

	return dep, provider, nil
}

// confirmPrompt asks the user to confirm an action. Returns true if confirmed.
func confirmPrompt(msg string) bool {
	fmt.Printf("%s [y/N]: ", msg)
	reader := bufio.NewReader(os.Stdin)
	answer, _ := reader.ReadString('\n')
	answer = strings.TrimSpace(strings.ToLower(answer))
	return answer == "y" || answer == "yes"
}

func newInstallCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "install",
		Short: "Deploy KMP to a new environment",
		RunE: func(cmd *cobra.Command, args []string) error {
			p := tea.NewProgram(tui.NewInstallModel(), tea.WithAltScreen())
			if _, err := p.Run(); err != nil {
				return fmt.Errorf("install TUI error: %w", err)
			}
			return nil
		},
	}
}

func newUpdateCmd() *cobra.Command {
	var (
		interactive bool
		channel     string
		yes         bool
		checkOnly   bool
	)

	cmd := &cobra.Command{
		Use:   "update",
		Short: "Check and apply updates",
		RunE: func(cmd *cobra.Command, args []string) error {
			if interactive {
				p := tea.NewProgram(tui.NewUpdateModel(), tea.WithAltScreen())
				if _, err := p.Run(); err != nil {
					return fmt.Errorf("update TUI error: %w", err)
				}
				return nil
			}

			dep, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			ch := dep.Channel
			if channel != "" {
				ch = channel
			}
			if ch == "" {
				ch = "release"
			}

			fmt.Printf("⠋ Checking for updates (channel: %s)...\n", ch)

			client := registry.NewClient()
			latest, err := client.GetLatestByChannel(ch)
			if err != nil {
				return fmt.Errorf("failed to check for updates: %w", err)
			}

			currentTag := dep.ImageTag
			fmt.Printf("  Current version: %s\n", currentTag)
			fmt.Printf("  Latest version:  %s\n", latest.Tag)

			if currentTag == latest.Tag {
				fmt.Println("✓ Already up to date!")
				return nil
			}

			if latest.Body != "" {
				fmt.Printf("\n  Changelog:\n  %s\n\n", strings.ReplaceAll(latest.Body, "\n", "\n  "))
			}

			if checkOnly {
				fmt.Println("ℹ Update available. Run without --check to apply.")
				return nil
			}

			if !yes {
				if !confirmPrompt(fmt.Sprintf("Update from %s to %s?", currentTag, latest.Tag)) {
					fmt.Println("Update cancelled.")
					return nil
				}
			}

			fmt.Printf("⠋ Updating to %s...\n", latest.Tag)
			if err := provider.Update(latest.Tag); err != nil {
				fmt.Println("✗ Update failed:", err)
				return err
			}

			fmt.Printf("✓ Successfully updated to %s\n", latest.Tag)
			return nil
		},
	}

	cmd.Flags().BoolVar(&interactive, "interactive", false, "Use interactive TUI mode")
	cmd.Flags().StringVar(&channel, "channel", "", "Release channel (release, beta, dev, nightly)")
	cmd.Flags().BoolVarP(&yes, "yes", "y", false, "Auto-confirm update")
	cmd.Flags().BoolVar(&checkOnly, "check", false, "Only check for updates, don't apply")

	return cmd
}

func newStatusCmd() *cobra.Command {
	var (
		interactive bool
		jsonOutput  bool
	)

	cmd := &cobra.Command{
		Use:   "status",
		Short: "Show deployment health",
		RunE: func(cmd *cobra.Command, args []string) error {
			if interactive {
				p := tea.NewProgram(tui.NewStatusModel(), tea.WithAltScreen())
				if _, err := p.Run(); err != nil {
					return fmt.Errorf("status TUI error: %w", err)
				}
				return nil
			}

			_, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			st, err := provider.Status()
			if err != nil {
				return fmt.Errorf("failed to get status: %w", err)
			}

			if jsonOutput {
				data, err := json.MarshalIndent(st, "", "  ")
				if err != nil {
					return err
				}
				fmt.Println(string(data))
				return nil
			}

			healthIcon := "✗"
			if st.Healthy {
				healthIcon = "✓"
			}
			runningIcon := "✗"
			if st.Running {
				runningIcon = "✓"
			}
			dbIcon := "✗"
			if st.DBConnected {
				dbIcon = "✓"
			}
			cacheIcon := "✗"
			if st.CacheOK {
				cacheIcon = "✓"
			}

			fmt.Println("KMP Deployment Status")
			fmt.Println("─────────────────────────────")
			fmt.Printf("  Provider:   %s\n", st.Provider)
			fmt.Printf("  Domain:     %s\n", st.Domain)
			fmt.Printf("  Version:    %s\n", st.Version)
			fmt.Printf("  Channel:    %s\n", st.Channel)
			fmt.Printf("  Running:    %s\n", runningIcon)
			fmt.Printf("  Healthy:    %s\n", healthIcon)
			fmt.Printf("  Database:   %s\n", dbIcon)
			fmt.Printf("  Cache:      %s\n", cacheIcon)
			if st.Uptime != "" {
				fmt.Printf("  Uptime:     %s\n", st.Uptime)
			}
			if st.LastBackup != "" {
				fmt.Printf("  Last Backup: %s\n", st.LastBackup)
			}
			if st.LastUpdate != "" {
				fmt.Printf("  Last Update: %s\n", st.LastUpdate)
			}
			return nil
		},
	}

	cmd.Flags().BoolVar(&interactive, "interactive", false, "Use interactive TUI mode")
	cmd.Flags().BoolVar(&jsonOutput, "json", false, "Output in JSON format")

	return cmd
}

func newLogsCmd() *cobra.Command {
	var follow bool

	cmd := &cobra.Command{
		Use:   "logs",
		Short: "View application logs",
		RunE: func(cmd *cobra.Command, args []string) error {
			_, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			reader, err := provider.Logs(follow)
			if err != nil {
				return fmt.Errorf("failed to get logs: %w", err)
			}
			defer reader.Close()

			if _, err := io.Copy(os.Stdout, reader); err != nil {
				// Ignore broken pipe errors when user cancels
				if !strings.Contains(err.Error(), "broken pipe") {
					return err
				}
			}
			return nil
		},
	}

	cmd.Flags().BoolVarP(&follow, "follow", "f", false, "Follow log output")

	return cmd
}

func newBackupCmd() *cobra.Command {
	var now bool

	cmd := &cobra.Command{
		Use:   "backup",
		Short: "Create a backup",
		RunE: func(cmd *cobra.Command, args []string) error {
			_, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			if !now {
				if !confirmPrompt("Create a backup now?") {
					fmt.Println("Backup cancelled.")
					return nil
				}
			}

			fmt.Println("⠋ Creating backup...")
			result, err := provider.Backup()
			if err != nil {
				fmt.Println("✗ Backup failed:", err)
				return err
			}

			fmt.Println("✓ Backup created successfully!")
			fmt.Printf("  ID:       %s\n", result.ID)
			fmt.Printf("  Size:     %d bytes\n", result.Size)
			fmt.Printf("  Location: %s\n", result.Location)
			return nil
		},
	}

	cmd.Flags().BoolVar(&now, "now", false, "Skip confirmation prompt")

	return cmd
}

func newRestoreCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "restore [backup-id]",
		Short: "Restore from backup",
		Args:  cobra.ExactArgs(1),
		RunE: func(cmd *cobra.Command, args []string) error {
			backupID := args[0]

			_, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			if !confirmPrompt(fmt.Sprintf("This will restore from backup %s. Current data will be lost. Continue?", backupID)) {
				fmt.Println("Restore cancelled.")
				return nil
			}

			fmt.Printf("⠋ Restoring from backup %s...\n", backupID)
			if err := provider.Restore(backupID); err != nil {
				fmt.Println("✗ Restore failed:", err)
				return err
			}

			fmt.Println("✓ Restore completed successfully!")
			return nil
		},
	}
}

func newRollbackCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "rollback",
		Short: "Revert to previous version",
		RunE: func(cmd *cobra.Command, args []string) error {
			_, provider, err := loadDeployment()
			if err != nil {
				return err
			}

			if !confirmPrompt("This will revert to the previous version. Continue?") {
				fmt.Println("Rollback cancelled.")
				return nil
			}

			fmt.Println("⠋ Rolling back to previous version...")
			if err := provider.Rollback(); err != nil {
				fmt.Println("✗ Rollback failed:", err)
				return err
			}

			fmt.Println("✓ Rollback completed successfully!")
			return nil
		},
	}
}

func newConfigCmd() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "config",
		Short: "View/edit deployment config",
	}

	showCmd := &cobra.Command{
		Use:   "show",
		Short: "Show current configuration",
		RunE: func(cmd *cobra.Command, args []string) error {
			path := config.ConfigPath()
			data, err := os.ReadFile(path)
			if err != nil {
				if os.IsNotExist(err) {
					fmt.Println("No configuration file found. Run `kmp install` first.")
					return nil
				}
				return err
			}
			fmt.Printf("# %s\n", path)
			fmt.Println(string(data))
			return nil
		},
	}

	pathCmd := &cobra.Command{
		Use:   "path",
		Short: "Show config file path",
		Run: func(cmd *cobra.Command, args []string) {
			fmt.Println(config.ConfigPath())
		},
	}

	cmd.AddCommand(showCmd, pathCmd)

	// Default to "show" when no subcommand given
	cmd.RunE = func(cmd *cobra.Command, args []string) error {
		return showCmd.RunE(showCmd, args)
	}

	return cmd
}

func newSelfUpdateCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "self-update",
		Short: "Update this tool to the latest version",
		RunE: func(cmd *cobra.Command, args []string) error {
			fmt.Printf("Current version: %s\n", version)
			fmt.Println("Checking for updates ...")
			if err := selfupdate.Perform(version); err != nil {
				return fmt.Errorf("self-update failed: %w", err)
			}
			return nil
		},
	}
}

func newVersionCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "version",
		Short: "Show version information",
		Run: func(cmd *cobra.Command, args []string) {
			fmt.Printf("kmp version %s\n", version)
		},
	}
}
