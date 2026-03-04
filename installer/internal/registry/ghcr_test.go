package registry

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestGHCRClientGetTagsHandlesBearerChallenge(t *testing.T) {
	tokenValue := "test-token"
	var tokenRequested bool

	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/v2/jhandel/kmp/tags/list":
			if r.Header.Get("Authorization") != "Bearer "+tokenValue {
				w.Header().Set(
					"WWW-Authenticate",
					`Bearer realm="https://`+r.Host+`/token",service="ghcr.io",scope="repository:jhandel/kmp:pull"`,
				)
				w.WriteHeader(http.StatusUnauthorized)
				return
			}
			_ = json.NewEncoder(w).Encode(map[string][]string{
				"tags": {"latest", "v1.10.0", "v1.9.0"},
			})
		case "/token":
			tokenRequested = true
			_ = json.NewEncoder(w).Encode(map[string]string{"token": tokenValue})
		default:
			http.NotFound(w, r)
		}
	}))
	defer server.Close()

	client := &GHCRClient{
		Image:      strings.TrimPrefix(server.URL, "https://") + "/jhandel/kmp",
		HTTPClient: server.Client(),
	}

	tags, err := client.GetTags()
	if err != nil {
		t.Fatalf("expected tags, got error: %v", err)
	}
	if !tokenRequested {
		t.Fatalf("expected token endpoint to be requested")
	}
	if len(tags) != 3 {
		t.Fatalf("expected 3 tags, got %d", len(tags))
	}
}

func TestGHCRClientGetLatestTagByChannelPrefersLatest(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v2/jhandel/kmp/tags/list" {
			http.NotFound(w, r)
			return
		}
		_ = json.NewEncoder(w).Encode(map[string][]string{
			"tags": {"v1.10.0", "latest", "v1.9.0"},
		})
	}))
	defer server.Close()

	client := &GHCRClient{
		Image:      strings.TrimPrefix(server.URL, "https://") + "/jhandel/kmp",
		HTTPClient: server.Client(),
	}

	tag, err := client.GetLatestTagByChannel("release")
	if err != nil {
		t.Fatalf("expected latest tag, got error: %v", err)
	}
	if tag != "latest" {
		t.Fatalf("expected latest, got %s", tag)
	}
}

func TestGHCRClientGetLatestTagByChannelUsesHighestSemver(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/v2/jhandel/kmp/tags/list" {
			http.NotFound(w, r)
			return
		}
		_ = json.NewEncoder(w).Encode(map[string][]string{
			"tags": {"v1.9.0", "v1.10.0", "not-a-version"},
		})
	}))
	defer server.Close()

	client := &GHCRClient{
		Image:      strings.TrimPrefix(server.URL, "https://") + "/jhandel/kmp",
		HTTPClient: server.Client(),
	}

	tag, err := client.GetLatestTagByChannel("release")
	if err != nil {
		t.Fatalf("expected latest semver tag, got error: %v", err)
	}
	if tag != "v1.10.0" {
		t.Fatalf("expected v1.10.0, got %s", tag)
	}
}
