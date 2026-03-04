package health

import (
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// Response represents the /health endpoint response
type Response struct {
	Status    string `json:"status"`
	Version   string `json:"version"`
	ImageTag  string `json:"image_tag"`
	Channel   string `json:"channel"`
	DB        bool   `json:"db"`
	Cache     bool   `json:"cache"`
	Profile   string `json:"profile"`
	Timestamp string `json:"timestamp"`
}

// Check queries the KMP health endpoint
func Check(baseURL string) (*Response, error) {
	client := &http.Client{Timeout: 5 * time.Second}

	url := fmt.Sprintf("%s/health", baseURL)
	resp, err := client.Get(url)
	if err != nil {
		return nil, fmt.Errorf("health check failed: %w", err)
	}
	defer resp.Body.Close()

	var health Response
	if err := json.NewDecoder(resp.Body).Decode(&health); err != nil {
		return nil, fmt.Errorf("invalid health response: %w", err)
	}

	return &health, nil
}

// IsHealthy returns true if the app is fully operational
func (r *Response) IsHealthy() bool {
	return r.Status == "ok" && r.DB && r.Cache
}
