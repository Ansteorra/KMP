package updater

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// runUpdate executes the full update sequence:
// 1. Record previous tag
// 2. Pull new image
// 3. Update .env with new tag
// 4. Recreate app container
// 5. Wait for health check
// 6. Auto-rollback on failure
func (s *Server) runUpdate(targetTag string) {
	imageRef := fmt.Sprintf("%s:%s", s.cfg.ImageRepo, targetTag)

	// Determine current tag from .env
	previousTag := s.readCurrentTag()

	s.mu.Lock()
	s.state.TargetTag = targetTag
	s.state.PreviousTag = previousTag
	s.mu.Unlock()

	// Step 1: Pull new image
	s.setState("pulling", fmt.Sprintf("Pulling %s...", imageRef), 10)
	if err := s.dockerComposeWithImageTag(targetTag, "pull", s.cfg.AppServiceName); err != nil {
		s.setState("failed", fmt.Sprintf("Pull failed: %v", err), 0)
		return
	}

	// Step 2: Update .env
	s.setState("stopping", "Updating image tag...", 30)
	if err := s.updateEnvTag(targetTag); err != nil {
		log.Printf("Warning: could not persist KMP_IMAGE_TAG to .env; continuing with runtime override: %v", err)
	}

	// Step 3: Recreate app container with new image
	s.setState("starting", "Recreating app container...", 50)
	if err := s.recreateAppContainer(targetTag); err != nil {
		log.Printf("Failed to start new container, rolling back to %s", previousTag)
		s.rollbackTag(previousTag)
		return
	}

	// Step 4: Wait for health check
	s.setState("health_check", "Waiting for health check...", 70)
	if err := s.waitForHealthy(120 * time.Second); err != nil {
		log.Printf("Health check failed, rolling back to %s: %v", previousTag, err)
		s.setState("rolling_back", "Health check failed, rolling back...", 80)
		s.rollbackTag(previousTag)
		return
	}

	s.setState("completed", fmt.Sprintf("Updated to %s", targetTag), 100)
}

// rollbackTag reverts to a previous image tag.
func (s *Server) rollbackTag(tag string) {
	if err := s.updateEnvTag(tag); err != nil {
		log.Printf("Warning: could not persist rollback tag to .env; continuing with runtime override: %v", err)
	}
	if err := s.recreateAppContainer(tag); err != nil {
		s.setState("failed", fmt.Sprintf("Rollback container restart failed: %v", err), 0)
		return
	}
	s.setState("failed", fmt.Sprintf("Rolled back to %s after update failure", tag), 0)
}

func (s *Server) recreateAppContainer(imageTag string) error {
	if err := s.dockerCompose("stop", s.cfg.AppServiceName); err != nil {
		log.Printf("Warning: failed to stop app service before recreate: %v", err)
	}
	if err := s.dockerCompose("rm", "-f", s.cfg.AppServiceName); err != nil {
		log.Printf("Warning: failed to remove app service before recreate: %v", err)
	}
	err := s.dockerComposeWithImageTag(imageTag, "up", "-d", "--no-deps", s.cfg.AppServiceName)
	if err == nil {
		return nil
	}
	if !isContainerNameConflict(err) {
		return err
	}

	log.Printf("Detected container name conflict for kmp-app, force-removing and retrying once")
	if rmErr := s.removeContainerByName("kmp-app"); rmErr != nil {
		return fmt.Errorf("%v (also failed to remove kmp-app: %w)", err, rmErr)
	}
	return s.dockerComposeWithImageTag(imageTag, "up", "-d", "--no-deps", s.cfg.AppServiceName)
}

func isContainerNameConflict(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "container name") && strings.Contains(msg, "is already in use by container")
}

func (s *Server) removeContainerByName(name string) error {
	if s.removeContainerFn != nil {
		return s.removeContainerFn(name)
	}

	cmd := exec.Command("docker", "rm", "-f", name)
	cmd.Env = s.composeEnv()
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("%s: %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// dockerCompose runs a docker compose command in the compose directory.
func (s *Server) dockerCompose(args ...string) error {
	return s.dockerComposeWithImageTag("", args...)
}

// dockerComposeWithImageTag runs docker compose with an optional KMP_IMAGE_TAG override.
func (s *Server) dockerComposeWithImageTag(imageTag string, args ...string) error {
	if s.dockerComposeFn != nil {
		return s.dockerComposeFn(args...)
	}

	fullArgs := append([]string{"compose"}, args...)
	cmd := exec.Command("docker", fullArgs...)
	cmd.Dir = s.cfg.ComposeDir
	cmd.Env = s.composeEnv()
	if imageTag != "" {
		cmd.Env = append(cmd.Env, fmt.Sprintf("KMP_IMAGE_TAG=%s", imageTag))
	}

	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("%s: %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// readCurrentTag reads the current image tag from the running app container,
// falling back to .env when inspect data is unavailable.
func (s *Server) readCurrentTag() string {
	if s.readCurrentTagFn != nil {
		return s.readCurrentTagFn()
	}
	if runningTag, err := s.readRunningTag(); err == nil && runningTag != "" {
		return runningTag
	}

	envPath := filepath.Join(s.cfg.ComposeDir, ".env")
	data, err := os.ReadFile(envPath)
	if err != nil {
		return "unknown"
	}

	for _, line := range strings.Split(string(data), "\n") {
		line = strings.TrimSpace(line)
		if strings.HasPrefix(line, "KMP_IMAGE_TAG=") {
			return strings.TrimPrefix(line, "KMP_IMAGE_TAG=")
		}
	}
	return "unknown"
}

func (s *Server) readRunningTag() (string, error) {
	inspectCmd := exec.Command("docker", "inspect", "--format", "{{.Config.Image}}", "kmp-app")
	inspectOut, err := inspectCmd.Output()
	if err != nil {
		return "", err
	}

	imageRef := strings.TrimSpace(string(inspectOut))
	if imageRef == "" {
		return "", fmt.Errorf("image ref not found")
	}

	refWithoutDigest := strings.SplitN(imageRef, "@", 2)[0]
	colonIdx := strings.LastIndex(refWithoutDigest, ":")
	if colonIdx == -1 || colonIdx < strings.LastIndex(refWithoutDigest, "/") {
		return "", fmt.Errorf("image tag not found in %q", imageRef)
	}

	return refWithoutDigest[colonIdx+1:], nil
}

func (s *Server) composeEnv() []string {
	return append(os.Environ(), fmt.Sprintf("COMPOSE_PROJECT_NAME=%s", s.composeProjectName()))
}

func (s *Server) composeProjectName() string {
	s.mu.Lock()
	if s.resolvedComposeProject != "" {
		project := s.resolvedComposeProject
		s.mu.Unlock()
		return project
	}
	if strings.TrimSpace(s.cfg.ComposeProject) != "" {
		s.resolvedComposeProject = strings.TrimSpace(s.cfg.ComposeProject)
		project := s.resolvedComposeProject
		s.mu.Unlock()
		return project
	}
	s.mu.Unlock()

	project, err := s.inspectComposeProject("kmp-updater")
	if err == nil && project != "" {
		s.mu.Lock()
		s.resolvedComposeProject = project
		s.mu.Unlock()
		return project
	}

	project, err = s.inspectComposeProject("kmp-app")
	if err == nil && project != "" {
		s.mu.Lock()
		s.resolvedComposeProject = project
		s.mu.Unlock()
		return project
	}

	return "kmp"
}

func (s *Server) inspectComposeProject(containerName string) (string, error) {
	inspectCmd := exec.Command("docker", "inspect", "--format", "{{ index .Config.Labels \"com.docker.compose.project\" }}", containerName)
	inspectOut, err := inspectCmd.Output()
	if err != nil {
		return "", err
	}
	return strings.TrimSpace(string(inspectOut)), nil
}

// updateEnvTag updates the KMP_IMAGE_TAG in .env to the given tag.
func (s *Server) updateEnvTag(tag string) error {
	if s.updateEnvTagFn != nil {
		return s.updateEnvTagFn(tag)
	}

	envPath := filepath.Join(s.cfg.ComposeDir, ".env")
	data, err := os.ReadFile(envPath)
	if err != nil {
		return fmt.Errorf("reading .env: %w", err)
	}

	lines := strings.Split(string(data), "\n")
	found := false
	for i, line := range lines {
		if strings.HasPrefix(strings.TrimSpace(line), "KMP_IMAGE_TAG=") {
			lines[i] = "KMP_IMAGE_TAG=" + tag
			found = true
			break
		}
	}
	if !found {
		lines = append(lines, "KMP_IMAGE_TAG="+tag)
	}

	return os.WriteFile(envPath, []byte(strings.Join(lines, "\n")), 0644)
}

// waitForHealthy polls the health endpoint until it returns healthy or timeout.
func (s *Server) waitForHealthy(timeout time.Duration) error {
	if s.waitForHealthyFn != nil {
		return s.waitForHealthyFn(timeout)
	}

	client := &http.Client{Timeout: 5 * time.Second}
	deadline := time.Now().Add(timeout)

	for time.Now().Before(deadline) {
		resp, err := client.Get(s.cfg.HealthURL)
		if err == nil {
			healthy := false
			if resp.StatusCode == http.StatusOK {
				var health struct {
					Status string `json:"status"`
					DB     bool   `json:"db"`
					Cache  bool   `json:"cache"`
				}
				if json.NewDecoder(resp.Body).Decode(&health) == nil {
					if health.Status == "ok" && health.DB {
						healthy = true
					}
				}
			}
			_, _ = io.Copy(io.Discard, resp.Body)
			_ = resp.Body.Close()
			if healthy {
				return nil
			}
		}
		time.Sleep(3 * time.Second)
	}

	return fmt.Errorf("health check timed out after %s", timeout)
}
