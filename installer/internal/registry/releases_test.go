package registry

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strconv"
	"sync"
	"testing"
)

func TestGetLatestByChannelPaginatesAndSkipsNonAppReleases(t *testing.T) {
	var (
		mu       sync.Mutex
		requests []string
	)

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		mu.Lock()
		requests = append(requests, r.URL.RawQuery)
		mu.Unlock()

		page, _ := strconv.Atoi(r.URL.Query().Get("page"))
		perPage, _ := strconv.Atoi(r.URL.Query().Get("per_page"))
		if perPage != 100 {
			t.Fatalf("expected per_page=100, got %d", perPage)
		}

		type payload struct {
			Name       string `json:"name"`
			Tag        string `json:"tag_name"`
			Published  string `json:"published_at"`
			Prerelease bool   `json:"prerelease"`
			Body       string `json:"body"`
			HTMLURL    string `json:"html_url"`
		}

		switch page {
		case 1:
			firstPage := make([]payload, 0, 100)
			for i := 0; i < 100; i++ {
				firstPage = append(firstPage, payload{
					Name: "Installer Release",
					Tag:  "installer-v2.0." + strconv.Itoa(i),
				})
			}
			_ = json.NewEncoder(w).Encode(firstPage)
		case 2:
			secondPage := []payload{
				{Name: "KMP v1.4.96", Tag: "v1.4.96", Published: "2026-02-23T00:00:00Z", Prerelease: false},
				{Name: "KMP v1.4.95", Tag: "v1.4.95", Published: "2026-02-22T00:00:00Z", Prerelease: false},
				{Name: "KMP v1.4.94", Tag: "v1.4.94", Published: "2026-02-21T00:00:00Z", Prerelease: false},
			}
			_ = json.NewEncoder(w).Encode(secondPage)
		default:
			_ = json.NewEncoder(w).Encode([]payload{})
		}
	}))
	defer server.Close()

	client := NewClient()
	client.APIBase = server.URL

	latest, err := client.GetLatestByChannel("release")
	if err != nil {
		t.Fatalf("expected latest release, got error: %v", err)
	}

	if latest.Tag != "v1.4.96" {
		t.Fatalf("expected latest release v1.4.96, got %s", latest.Tag)
	}

	mu.Lock()
	defer mu.Unlock()
	if len(requests) < 2 {
		t.Fatalf("expected at least 2 paginated requests, got %d", len(requests))
	}
}
