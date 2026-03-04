package registry

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const (
	defaultRepo    = "jhandel/KMP"
	defaultImage   = "ghcr.io/jhandel/kmp"
	defaultAPIBase = "https://api.github.com"
	apiPath        = "/repos/%s/releases"
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
	APIBase    string
	HTTPClient *http.Client
}

// NewClient creates a new release registry client
func NewClient() *Client {
	return &Client{
		Repo:       defaultRepo,
		APIBase:    defaultAPIBase,
		HTTPClient: &http.Client{Timeout: 10 * time.Second},
	}
}

// ImageForTag returns the full image reference for a tag
func ImageForTag(tag string) string {
	return fmt.Sprintf("%s:%s", defaultImage, tag)
}

// GetReleases fetches releases from GitHub
func (c *Client) GetReleases(limit int) ([]Release, error) {
	perPage := 100
	page := 1
	collected := make([]Release, 0)

	for {
		requestURL := fmt.Sprintf("%s"+apiPath, strings.TrimRight(c.APIBase, "/"), c.Repo)
		parsedURL, err := url.Parse(requestURL)
		if err != nil {
			return nil, err
		}
		query := parsedURL.Query()
		query.Set("per_page", fmt.Sprintf("%d", perPage))
		query.Set("page", fmt.Sprintf("%d", page))
		parsedURL.RawQuery = query.Encode()

		req, err := http.NewRequest("GET", parsedURL.String(), nil)
		if err != nil {
			return nil, err
		}
		req.Header.Set("Accept", "application/vnd.github.v3+json")

		resp, err := c.HTTPClient.Do(req)
		if err != nil {
			return nil, fmt.Errorf("failed to fetch releases: %w", err)
		}

		if resp.StatusCode != 200 {
			resp.Body.Close()
			return nil, fmt.Errorf("GitHub API returned %d", resp.StatusCode)
		}

		var pageReleases []Release
		decodeErr := json.NewDecoder(resp.Body).Decode(&pageReleases)
		resp.Body.Close()
		if decodeErr != nil {
			return nil, decodeErr
		}

		if len(pageReleases) == 0 {
			break
		}

		for i := range pageReleases {
			if !isAppReleaseTag(pageReleases[i].Tag) {
				continue
			}
			pageReleases[i].Channel = classifyChannel(pageReleases[i])
			collected = append(collected, pageReleases[i])
			if limit > 0 && len(collected) >= limit {
				return collected[:limit], nil
			}
		}

		if len(pageReleases) < perPage {
			break
		}
		page++
	}

	return collected, nil
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

func isAppReleaseTag(tag string) bool {
	normalized := strings.ToLower(strings.TrimSpace(tag))
	return normalized != "" &&
		!strings.HasPrefix(normalized, "installer-") &&
		!strings.HasPrefix(normalized, "updater-")
}
