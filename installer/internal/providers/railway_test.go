package providers

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
)

func TestRunRailwayMigrationsRetriesTransientSSHFailure(t *testing.T) {
	tempDir := t.TempDir()
	countFile := filepath.Join(tempDir, "count")
	mockRailway := filepath.Join(tempDir, "railway")

	script := "#!/bin/sh\n" +
		"count_file=\"" + countFile + "\"\n" +
		"count=0\n" +
		"if [ -f \"$count_file\" ]; then count=$(cat \"$count_file\"); fi\n" +
		"count=$((count + 1))\n" +
		"echo \"$count\" > \"$count_file\"\n" +
		"if [ \"$count\" -eq 1 ]; then\n" +
		"  echo \"mock transient ssh failure\" >&2\n" +
		"  exit 1\n" +
		"fi\n" +
		"exit 0\n"

	if err := os.WriteFile(mockRailway, []byte(script), 0o755); err != nil {
		t.Fatalf("write mock railway script: %v", err)
	}

	t.Setenv("PATH", tempDir+string(os.PathListSeparator)+os.Getenv("PATH"))

	if err := runRailwayMigrations("kmp-app"); err != nil {
		t.Fatalf("runRailwayMigrations returned error: %v", err)
	}

	rawCount, err := os.ReadFile(countFile)
	if err != nil {
		t.Fatalf("read invocation count: %v", err)
	}

	count, err := strconv.Atoi(strings.TrimSpace(string(rawCount)))
	if err != nil {
		t.Fatalf("parse invocation count: %v", err)
	}

	if count < 7 {
		t.Fatalf("expected at least 7 railway invocations (retry + migration commands), got %d", count)
	}
}
