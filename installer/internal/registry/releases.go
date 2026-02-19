package registry

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"time"
)

const (
	defaultRepo  = "jhandel/KMP"
	defaultImage = "ghcr.io/jhandel/kmp"
	apiURL       = "https://api.github.com/repos/%s/releases"
)

// Release represents a KMP release
type Release struct {
	Name       string `json:"name"`
	Tag        string `json:"tag_name"`
	Channel    string `json:"-"`
	Published  string `json:"published_at"`
	Prerelease bool   `json:"prerelease"`
	Body       string `json:"body"`
	HTMLURL    string `json:"html_url"`
}

// Client fetches release information from GitHub
type Client struct {
	Repo       string
	HTTPClient *http.Client
}

// NewClient creates a new release registry client
func NewClient() *Client {
	return &Client{
		Repo:       defaultRepo,
		HTTPClient: &http.Client{Timeout: 10 * time.Second},
	}
}

// ImageForTag returns the full image reference for a tag
func ImageForTag(tag string) string {
	return fmt.Sprintf("%s:%s", defaultImage, tag)
}

// GetReleases fetches releases from GitHub
func (c *Client) GetReleases(limit int) ([]Release, error) {
	url := fmt.Sprintf(apiURL, c.Repo)

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/vnd.github.v3+json")

	resp, err := c.HTTPClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("failed to fetch releases: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("GitHub API returned %d", resp.StatusCode)
	}

	var releases []Release
	if err := json.NewDecoder(resp.Body).Decode(&releases); err != nil {
		return nil, err
	}

	// Classify channels
	for i := range releases {
		releases[i].Channel = classifyChannel(releases[i])
	}

	if limit > 0 && len(releases) > limit {
		releases = releases[:limit]
	}

	return releases, nil
}

// GetLatestByChannel returns the latest release for a channel
func (c *Client) GetLatestByChannel(channel string) (*Release, error) {
	releases, err := c.GetReleases(50)
	if err != nil {
		return nil, err
	}

	for _, r := range releases {
		if r.Channel == channel {
			return &r, nil
		}
	}

	return nil, fmt.Errorf("no releases found for channel %q", channel)
}

func classifyChannel(r Release) string {
	tag := strings.ToLower(r.Tag)
	name := strings.ToLower(r.Name)

	if strings.Contains(tag, "nightly") || strings.Contains(name, "nightly") {
		return "nightly"
	}
	if strings.Contains(tag, "dev") || strings.Contains(name, "dev") {
		return "dev"
	}
	if r.Prerelease || strings.Contains(tag, "beta") || strings.Contains(tag, "rc") {
		return "beta"
	}
	return "release"
}
