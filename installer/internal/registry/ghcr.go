package registry

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"

	"golang.org/x/mod/semver"
)

// Tag represents a container image tag from the registry.
type Tag struct {
	Name    string
	Channel string
}

// GHCRClient queries the GitHub Container Registry OCI Distribution API.
type GHCRClient struct {
	Image      string // e.g. "ghcr.io/jhandel/kmp"
	HTTPClient *http.Client
}

// NewGHCRClient creates a client for querying GHCR tags.
func NewGHCRClient() *GHCRClient {
	return &GHCRClient{
		Image:      defaultImage,
		HTTPClient: &http.Client{Timeout: 10 * time.Second},
	}
}

// GetTags fetches available image tags from the GHCR OCI Distribution API.
func (g *GHCRClient) GetTags() ([]Tag, error) {
	parts := strings.SplitN(g.Image, "/", 2)
	if len(parts) != 2 {
		return nil, fmt.Errorf("invalid image reference: %s", g.Image)
	}
	host := parts[0]
	path := parts[1]

	url := fmt.Sprintf("https://%s/v2/%s/tags/list", host, path)
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")

	resp, err := g.httpClient().Do(req)
	if err != nil {
		return nil, fmt.Errorf("GHCR tag fetch failed: %w", err)
	}
	if resp.StatusCode == http.StatusUnauthorized {
		token, tokenErr := g.getBearerToken(resp.Header.Get("WWW-Authenticate"))
		resp.Body.Close()
		if tokenErr != nil {
			return nil, tokenErr
		}

		req.Header.Set("Authorization", "Bearer "+token)
		resp, err = g.httpClient().Do(req)
		if err != nil {
			return nil, fmt.Errorf("GHCR tag fetch retry failed: %w", err)
		}
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("GHCR API returned %d", resp.StatusCode)
	}

	var result struct {
		Tags []string `json:"tags"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	var tags []Tag
	for _, t := range result.Tags {
		// Filter out non-app tags: base images, digests, installer/updater releases
		if strings.HasPrefix(t, "php") || strings.HasPrefix(t, "sha256-") ||
			strings.HasPrefix(t, "sha-") || strings.HasPrefix(t, "installer-") ||
			strings.HasPrefix(t, "updater-") {
			continue
		}
		tags = append(tags, Tag{
			Name:    t,
			Channel: classifyTag(t),
		})
	}

	return tags, nil
}

// GetLatestTagByChannel returns the most recent tag for a channel from GHCR.
func (g *GHCRClient) GetLatestTagByChannel(channel string) (string, error) {
	tags, err := g.GetTags()
	if err != nil {
		return "", err
	}

	bestTag := ""
	bestVersion := ""
	for _, t := range tags {
		if t.Channel != channel {
			continue
		}
		if t.Name == "latest" {
			return t.Name, nil
		}
		version := t.Name
		if !strings.HasPrefix(version, "v") {
			version = "v" + version
		}
		if !semver.IsValid(version) {
			continue
		}
		if bestVersion == "" || semver.Compare(version, bestVersion) > 0 {
			bestVersion = version
			bestTag = t.Name
		}
	}
	if bestTag != "" {
		return bestTag, nil
	}
	return "", fmt.Errorf("no tags found for channel %q", channel)
}

func (g *GHCRClient) httpClient() *http.Client {
	if g.HTTPClient != nil {
		return g.HTTPClient
	}

	return &http.Client{Timeout: 10 * time.Second}
}

func (g *GHCRClient) getBearerToken(wwwAuthenticate string) (string, error) {
	realm, service, scope, ok := parseBearerChallenge(wwwAuthenticate)
	if !ok {
		return "", fmt.Errorf("GHCR API returned %d", http.StatusUnauthorized)
	}

	tokenURL, err := url.Parse(realm)
	if err != nil {
		return "", err
	}
	query := tokenURL.Query()
	if service != "" {
		query.Set("service", service)
	}
	if scope != "" {
		query.Set("scope", scope)
	}
	tokenURL.RawQuery = query.Encode()

	tokenReq, err := http.NewRequest("GET", tokenURL.String(), nil)
	if err != nil {
		return "", err
	}
	tokenReq.Header.Set("Accept", "application/json")

	tokenResp, err := g.httpClient().Do(tokenReq)
	if err != nil {
		return "", err
	}
	defer tokenResp.Body.Close()
	if tokenResp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("GHCR token API returned %d", tokenResp.StatusCode)
	}

	var tokenPayload struct {
		Token       string `json:"token"`
		AccessToken string `json:"access_token"`
	}
	if err := json.NewDecoder(tokenResp.Body).Decode(&tokenPayload); err != nil {
		return "", err
	}
	if tokenPayload.Token != "" {
		return tokenPayload.Token, nil
	}
	if tokenPayload.AccessToken != "" {
		return tokenPayload.AccessToken, nil
	}

	return "", fmt.Errorf("GHCR token API returned no token")
}

func parseBearerChallenge(header string) (realm string, service string, scope string, ok bool) {
	prefix := "bearer "
	if !strings.HasPrefix(strings.ToLower(header), prefix) {
		return "", "", "", false
	}

	params := strings.Split(header[len(prefix):], ",")
	values := map[string]string{}
	for _, part := range params {
		kv := strings.SplitN(strings.TrimSpace(part), "=", 2)
		if len(kv) != 2 {
			continue
		}
		values[strings.ToLower(kv[0])] = strings.Trim(kv[1], "\"")
	}

	realm = values["realm"]
	if realm == "" {
		return "", "", "", false
	}
	return realm, values["service"], values["scope"], true
}

func classifyTag(tag string) string {
	lower := strings.ToLower(tag)
	if strings.Contains(lower, "nightly") {
		return "nightly"
	}
	if strings.Contains(lower, "dev") {
		return "dev"
	}
	if strings.Contains(lower, "beta") || strings.Contains(lower, "rc") || strings.Contains(lower, "alpha") {
		return "beta"
	}
	if tag == "latest" {
		return "release"
	}
	return "release"
}
