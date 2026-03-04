package updater

import (
	"bytes"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"reflect"
	"strings"
	"testing"
	"time"
)

func TestHandleUpdateRequiresTargetTag(t *testing.T) {
	s := NewServer(Config{})
	req := httptest.NewRequest(http.MethodPost, "/updater/update", bytes.NewBufferString(`{}`))
	rec := httptest.NewRecorder()

	s.handleUpdate(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Fatalf("expected status 400, got %d", rec.Code)
	}
}

func TestHandleUpdateConflictWhenBusy(t *testing.T) {
	s := NewServer(Config{})
	s.setState("pulling", "busy", 10)

	req := httptest.NewRequest(http.MethodPost, "/updater/update", bytes.NewBufferString(`{"targetTag":"v1.2.3"}`))
	rec := httptest.NewRecorder()

	s.handleUpdate(rec, req)

	if rec.Code != http.StatusConflict {
		t.Fatalf("expected status 409, got %d", rec.Code)
	}
}

func TestHandleUpdateRunsToCompletion(t *testing.T) {
	s := NewServer(Config{AppServiceName: "app", ImageRepo: "ghcr.io/jhandel/kmp"})
	s.runAsync = func(fn func()) { fn() }
	s.readCurrentTagFn = func() string { return "v1.0.0" }
	s.updateEnvTagFn = func(string) error { return nil }
	s.dockerComposeFn = func(args ...string) error { return nil }
	s.waitForHealthyFn = func(time.Duration) error { return nil }

	req := httptest.NewRequest(http.MethodPost, "/updater/update", bytes.NewBufferString(`{"targetTag":"v1.1.0"}`))
	rec := httptest.NewRecorder()

	s.handleUpdate(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rec.Code)
	}

	st := readState(s)
	if st.Status != "completed" {
		t.Fatalf("expected completed status, got %q (%s)", st.Status, st.Message)
	}
	if st.TargetTag != "v1.1.0" {
		t.Fatalf("expected target tag v1.1.0, got %q", st.TargetTag)
	}
	if st.PreviousTag != "v1.0.0" {
		t.Fatalf("expected previous tag v1.0.0, got %q", st.PreviousTag)
	}
}

func TestHandleUpdateReservesStateBeforeAsyncRun(t *testing.T) {
	s := NewServer(Config{})
	runAsyncCalled := false
	s.runAsync = func(fn func()) {
		runAsyncCalled = true
	}

	req := httptest.NewRequest(http.MethodPost, "/updater/update", bytes.NewBufferString(`{"targetTag":"v1.1.0"}`))
	rec := httptest.NewRecorder()

	s.handleUpdate(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rec.Code)
	}
	if !runAsyncCalled {
		t.Fatal("expected async update to be scheduled")
	}
	st := readState(s)
	if st.Status != "pulling" {
		t.Fatalf("expected reserved pulling status, got %q", st.Status)
	}
	if st.TargetTag != "v1.1.0" {
		t.Fatalf("expected target tag v1.1.0, got %q", st.TargetTag)
	}
}

func TestHandleRollbackConflictWhenBusy(t *testing.T) {
	s := NewServer(Config{})
	s.setState("pulling", "busy", 10)

	req := httptest.NewRequest(http.MethodPost, "/updater/rollback", bytes.NewBufferString(`{"previousTag":"v1.0.0"}`))
	rec := httptest.NewRecorder()

	s.handleRollback(rec, req)

	if rec.Code != http.StatusConflict {
		t.Fatalf("expected status 409, got %d", rec.Code)
	}
}

func TestHandleRollbackReservesStateBeforeAsyncRun(t *testing.T) {
	s := NewServer(Config{})
	runAsyncCalled := false
	s.runAsync = func(fn func()) {
		runAsyncCalled = true
	}

	req := httptest.NewRequest(http.MethodPost, "/updater/rollback", bytes.NewBufferString(`{"previousTag":"v1.0.0"}`))
	rec := httptest.NewRecorder()

	s.handleRollback(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rec.Code)
	}
	if !runAsyncCalled {
		t.Fatal("expected async rollback to be scheduled")
	}
	st := readState(s)
	if st.Status != "rolling_back" {
		t.Fatalf("expected reserved rolling_back status, got %q", st.Status)
	}
	if st.TargetTag != "v1.0.0" {
		t.Fatalf("expected rollback target tag v1.0.0, got %q", st.TargetTag)
	}
}

func TestRunUpdateRollsBackOnHealthFailure(t *testing.T) {
	s := NewServer(Config{AppServiceName: "app", ImageRepo: "ghcr.io/jhandel/kmp"})
	s.readCurrentTagFn = func() string { return "v1.0.0" }
	var envTags []string
	s.updateEnvTagFn = func(tag string) error {
		envTags = append(envTags, tag)
		return nil
	}
	s.dockerComposeFn = func(args ...string) error { return nil }
	s.waitForHealthyFn = func(time.Duration) error { return errors.New("health failed") }

	s.runUpdate("v1.1.0")

	st := readState(s)
	if st.Status != "failed" {
		t.Fatalf("expected failed status, got %q", st.Status)
	}
	if !strings.Contains(st.Message, "Rolled back to v1.0.0") {
		t.Fatalf("expected rollback message, got %q", st.Message)
	}
	if len(envTags) != 2 || envTags[0] != "v1.1.0" || envTags[1] != "v1.0.0" {
		t.Fatalf("expected env tags [v1.1.0 v1.0.0], got %#v", envTags)
	}
}

func TestRunUpdateContinuesWhenEnvWriteFails(t *testing.T) {
	s := NewServer(Config{AppServiceName: "app", ImageRepo: "ghcr.io/jhandel/kmp"})
	s.readCurrentTagFn = func() string { return "v1.0.0" }
	s.updateEnvTagFn = func(string) error { return errors.New("read-only file system") }
	s.dockerComposeFn = func(args ...string) error { return nil }
	s.waitForHealthyFn = func(time.Duration) error { return nil }

	s.runUpdate("v1.1.0")

	st := readState(s)
	if st.Status != "completed" {
		t.Fatalf("expected completed status, got %q (%s)", st.Status, st.Message)
	}
}

func TestRecreateAppContainerStopsAndRemovesBeforeUp(t *testing.T) {
	s := NewServer(Config{AppServiceName: "app"})
	var calls [][]string
	s.dockerComposeFn = func(args ...string) error {
		calls = append(calls, append([]string{}, args...))
		return nil
	}

	if err := s.recreateAppContainer("v1.2.3"); err != nil {
		t.Fatalf("recreateAppContainer failed: %v", err)
	}

	expected := [][]string{
		{"stop", "app"},
		{"rm", "-f", "app"},
		{"up", "-d", "--no-deps", "app"},
	}
	if !reflect.DeepEqual(calls, expected) {
		t.Fatalf("expected calls %#v, got %#v", expected, calls)
	}
}

func TestRecreateAppContainerRetriesAfterNameConflict(t *testing.T) {
	s := NewServer(Config{AppServiceName: "app"})
	var calls [][]string
	upAttempts := 0
	s.dockerComposeFn = func(args ...string) error {
		calls = append(calls, append([]string{}, args...))
		if len(args) > 0 && args[0] == "up" {
			upAttempts++
			if upAttempts == 1 {
				return errors.New(`exit status 1: The container name "/kmp-app" is already in use by container "abc123"`)
			}
		}
		return nil
	}
	var removed string
	s.removeContainerFn = func(name string) error {
		removed = name
		return nil
	}

	if err := s.recreateAppContainer("v1.2.3"); err != nil {
		t.Fatalf("recreateAppContainer failed: %v", err)
	}
	if removed != "kmp-app" {
		t.Fatalf("expected removal of kmp-app, got %q", removed)
	}
	if upAttempts != 2 {
		t.Fatalf("expected 2 up attempts, got %d", upAttempts)
	}
}

func TestComposeProjectNameUsesConfig(t *testing.T) {
	s := NewServer(Config{ComposeProject: "kmp-d7d1ec11"})
	if got := s.composeProjectName(); got != "kmp-d7d1ec11" {
		t.Fatalf("expected configured compose project, got %q", got)
	}
}

func TestUpdateEnvTagWritesEnvFile(t *testing.T) {
	tmp := t.TempDir()
	envPath := filepath.Join(tmp, ".env")
	if err := os.WriteFile(envPath, []byte("APP_NAME=KMP\nKMP_IMAGE_TAG=v1.0.0\n"), 0644); err != nil {
		t.Fatalf("write env file: %v", err)
	}

	s := NewServer(Config{ComposeDir: tmp})
	if err := s.updateEnvTag("v1.1.0"); err != nil {
		t.Fatalf("updateEnvTag failed: %v", err)
	}

	data, err := os.ReadFile(envPath)
	if err != nil {
		t.Fatalf("read env file: %v", err)
	}
	if !strings.Contains(string(data), "KMP_IMAGE_TAG=v1.1.0") {
		t.Fatalf("expected updated tag in env file, got %q", string(data))
	}
}

func TestWaitForHealthySuccess(t *testing.T) {
	ts := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_ = json.NewEncoder(w).Encode(map[string]any{
			"status": "ok",
			"db":     true,
			"cache":  true,
		})
	}))
	defer ts.Close()

	s := NewServer(Config{HealthURL: ts.URL})
	if err := s.waitForHealthy(1 * time.Second); err != nil {
		t.Fatalf("expected healthy response, got error: %v", err)
	}
}

func readState(s *Server) State {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.state
}
