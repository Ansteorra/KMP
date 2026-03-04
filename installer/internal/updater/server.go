package updater

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"sync"
	"time"
)

// Config holds the updater sidecar configuration.
type Config struct {
	ComposeDir     string
	ComposeProject string
	AppServiceName string
	HealthURL      string
	ListenAddr     string
	ImageRepo      string
}

// State tracks the current update operation.
type State struct {
	Status      string `json:"status"` // idle, pulling, stopping, starting, health_check, completed, failed, rolling_back
	Message     string `json:"message"`
	Progress    int    `json:"progress"` // 0-100
	TargetTag   string `json:"targetTag"`
	PreviousTag string `json:"previousTag"`
}

// Server is the HTTP API server for the updater sidecar.
type Server struct {
	cfg   Config
	state State
	mu    sync.Mutex

	runAsync          func(func())
	readCurrentTagFn  func() string
	updateEnvTagFn    func(string) error
	dockerComposeFn   func(args ...string) error
	removeContainerFn func(string) error
	waitForHealthyFn  func(time.Duration) error

	resolvedComposeProject string
}

// NewServer creates a new updater server.
func NewServer(cfg Config) *Server {
	return &Server{
		cfg:   cfg,
		state: State{Status: "idle", Message: "Ready", Progress: 0},
		runAsync: func(fn func()) {
			go fn()
		},
	}
}

// Run starts the HTTP server.
func (s *Server) Run() error {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /updater/status", s.handleStatus)
	mux.HandleFunc("POST /updater/update", s.handleUpdate)
	mux.HandleFunc("POST /updater/rollback", s.handleRollback)

	return http.ListenAndServe(s.cfg.ListenAddr, mux)
}

func (s *Server) handleStatus(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	state := s.state
	s.mu.Unlock()

	writeJSON(w, state)
}

func (s *Server) handleUpdate(w http.ResponseWriter, r *http.Request) {
	var req struct {
		TargetTag string `json:"targetTag"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.TargetTag == "" {
		writeJSONError(w, "targetTag is required", http.StatusBadRequest)
		return
	}

	s.mu.Lock()
	if s.state.Status != "idle" && s.state.Status != "completed" && s.state.Status != "failed" {
		s.mu.Unlock()
		writeJSONError(w, fmt.Sprintf("update already in progress: %s", s.state.Status), http.StatusConflict)
		return
	}
	s.state.Status = "pulling"
	s.state.Message = "Update queued"
	s.state.Progress = 1
	s.state.TargetTag = req.TargetTag
	s.mu.Unlock()

	// Run update in background
	s.runAsync(func() {
		s.runUpdate(req.TargetTag)
	})

	writeJSON(w, map[string]string{"status": "started", "message": "Update initiated"})
}

func (s *Server) handleRollback(w http.ResponseWriter, r *http.Request) {
	var req struct {
		PreviousTag string `json:"previousTag"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.PreviousTag == "" {
		writeJSONError(w, "previousTag is required", http.StatusBadRequest)
		return
	}

	s.mu.Lock()
	if s.state.Status != "idle" && s.state.Status != "completed" && s.state.Status != "failed" {
		s.mu.Unlock()
		writeJSONError(w, "operation in progress", http.StatusConflict)
		return
	}
	s.state.Status = "rolling_back"
	s.state.Message = "Rollback queued"
	s.state.Progress = 1
	s.state.TargetTag = req.PreviousTag
	s.mu.Unlock()

	s.runAsync(func() {
		s.runUpdate(req.PreviousTag)
	})

	writeJSON(w, map[string]string{"status": "started", "message": "Rollback initiated"})
}

func writeJSON(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(data)
}

func writeJSONError(w http.ResponseWriter, msg string, code int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": msg})
}

func (s *Server) setState(status, message string, progress int) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.state.Status = status
	s.state.Message = message
	s.state.Progress = progress
	log.Printf("[update] %s: %s (%d%%)", status, message, progress)
}
