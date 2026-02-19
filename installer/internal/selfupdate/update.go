package selfupdate

import (
	"bufio"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"runtime"
	"strings"
	"time"
)

const (
	installerRepo = "jhandel/KMP"
	releasesAPI   = "https://api.github.com/repos/%s/releases"
)

// Check looks for a newer version of the kmp CLI tool.
// Returns availability, latest version, download URL, and checksums URL.
func Check(currentVersion string) (available bool, latestVersion string, downloadURL string, checksumURL string) {
	if currentVersion == "dev" {
		return false, "", "", ""
	}

	client := &http.Client{Timeout: 5 * time.Second}
	url := fmt.Sprintf(releasesAPI, installerRepo)

	resp, err := client.Get(url)
	if err != nil {
		return false, "", "", ""
	}
	defer resp.Body.Close()

	var releases []struct {
		TagName string `json:"tag_name"`
		Assets  []struct {
			Name               string `json:"name"`
			BrowserDownloadURL string `json:"browser_download_url"`
		} `json:"assets"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&releases); err != nil {
		return false, "", "", ""
	}

	// Look for installer releases (tagged installer-v*)
	for _, r := range releases {
		if !strings.HasPrefix(r.TagName, "installer-v") {
			continue
		}

		version := strings.TrimPrefix(r.TagName, "installer-v")
		if version == currentVersion {
			return false, "", "", ""
		}

		// Find asset for current platform
		assetName := fmt.Sprintf("kmp-%s-%s", runtime.GOOS, runtime.GOARCH)
		if runtime.GOOS == "windows" {
			assetName += ".exe"
		}

		var binaryURL, csURL string
		for _, a := range r.Assets {
			if a.Name == assetName {
				binaryURL = a.BrowserDownloadURL
			}
			if a.Name == "checksums.txt" {
				csURL = a.BrowserDownloadURL
			}
		}

		if binaryURL != "" {
			return true, version, binaryURL, csURL
		}

		// Only check the latest installer release
		break
	}

	return false, "", "", ""
}

// CheckAndNotify prints a notice if a newer version is available (non-blocking, swallows errors).
func CheckAndNotify(currentVersion string) {
	available, latestVersion, _, _ := Check(currentVersion)
	if available {
		fmt.Fprintf(os.Stderr, "\n  📦 KMP Installer v%s is available (you have v%s)\n", latestVersion, currentVersion)
		fmt.Fprintf(os.Stderr, "  Run `kmp self-update` to upgrade.\n\n")
	}
}

// Perform downloads and replaces the current binary with the latest version.
func Perform(currentVersion string) error {
	available, latestVersion, downloadURL, checksumURL := Check(currentVersion)
	if !available {
		return fmt.Errorf("already at latest version %s", currentVersion)
	}

	fmt.Printf("Downloading v%s ...\n", latestVersion)

	// Download new binary to temp file
	tmpFile, err := downloadToTemp(downloadURL)
	if err != nil {
		return fmt.Errorf("download failed: %w", err)
	}
	defer os.Remove(tmpFile) // clean up on any error path

	// Verify checksum if available
	if checksumURL != "" {
		fmt.Println("Verifying checksum ...")
		if err := verifyChecksum(tmpFile, checksumURL); err != nil {
			return fmt.Errorf("checksum verification failed: %w", err)
		}
	}

	// Get path to currently running binary
	execPath, err := os.Executable()
	if err != nil {
		return fmt.Errorf("cannot determine executable path: %w", err)
	}

	// Self-replace: rename current → .old, move new → current
	oldPath := execPath + ".old"
	_ = os.Remove(oldPath) // clean up any leftover .old from a previous run

	fmt.Println("Replacing binary ...")
	if err := os.Rename(execPath, oldPath); err != nil {
		return fmt.Errorf("failed to back up current binary: %w", err)
	}

	if err := os.Rename(tmpFile, execPath); err != nil {
		// Try to restore the old binary
		_ = os.Rename(oldPath, execPath)
		return fmt.Errorf("failed to install new binary: %w", err)
	}

	// Set executable permissions on Unix
	if runtime.GOOS != "windows" {
		if err := os.Chmod(execPath, 0755); err != nil {
			return fmt.Errorf("failed to set permissions: %w", err)
		}
	}

	// Remove .old file (on Windows this may fail if still in use; that's OK)
	_ = os.Remove(oldPath)

	fmt.Printf("✅ Successfully updated to v%s\n", latestVersion)
	return nil
}

// downloadToTemp downloads a URL to a temporary file and returns its path.
func downloadToTemp(url string) (string, error) {
	client := &http.Client{Timeout: 5 * time.Minute}
	resp, err := client.Get(url)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("unexpected status %d", resp.StatusCode)
	}

	tmp, err := os.CreateTemp("", "kmp-update-*")
	if err != nil {
		return "", err
	}

	if _, err := io.Copy(tmp, resp.Body); err != nil {
		tmp.Close()
		os.Remove(tmp.Name())
		return "", err
	}

	// Verify file has content
	info, err := tmp.Stat()
	if err != nil || info.Size() == 0 {
		tmp.Close()
		os.Remove(tmp.Name())
		return "", fmt.Errorf("downloaded file is empty")
	}

	tmp.Close()
	return tmp.Name(), nil
}

// verifyChecksum downloads checksums.txt and verifies the temp binary matches.
func verifyChecksum(filePath string, checksumURL string) error {
	// Download checksums.txt
	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Get(checksumURL)
	if err != nil {
		return fmt.Errorf("failed to download checksums: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("checksums request returned status %d", resp.StatusCode)
	}

	// Determine expected asset name
	assetName := fmt.Sprintf("kmp-%s-%s", runtime.GOOS, runtime.GOARCH)
	if runtime.GOOS == "windows" {
		assetName += ".exe"
	}

	// Parse checksums.txt (format: "<hash>  <filename>" per line)
	var expectedHash string
	scanner := bufio.NewScanner(resp.Body)
	for scanner.Scan() {
		line := scanner.Text()
		parts := strings.Fields(line)
		if len(parts) == 2 && parts[1] == assetName {
			expectedHash = parts[0]
			break
		}
	}

	if expectedHash == "" {
		return fmt.Errorf("no checksum found for %s", assetName)
	}

	// Compute SHA-256 of the downloaded file
	f, err := os.Open(filePath)
	if err != nil {
		return err
	}
	defer f.Close()

	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return err
	}
	actualHash := hex.EncodeToString(h.Sum(nil))

	if actualHash != expectedHash {
		return fmt.Errorf("hash mismatch: expected %s, got %s", expectedHash, actualHash)
	}

	return nil
}
