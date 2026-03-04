VERSION ?= dev
LDFLAGS := -ldflags "-X main.version=$(VERSION) -s -w"
BINARY := kmp
GOFLAGS := -trimpath

.PHONY: build clean test test-sidecar install all

build:
	go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY) ./cmd/kmp

install:
	go install $(LDFLAGS) $(GOFLAGS) ./cmd/kmp

test:
	go test ./...

test-sidecar:
	go test -v ./internal/updater

clean:
	rm -rf bin/

# Cross-compilation targets
all: linux-amd64 linux-arm64 darwin-amd64 darwin-arm64 windows-amd64

linux-amd64:
	GOOS=linux GOARCH=amd64 go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY)-linux-amd64 ./cmd/kmp

linux-arm64:
	GOOS=linux GOARCH=arm64 go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY)-linux-arm64 ./cmd/kmp

darwin-amd64:
	GOOS=darwin GOARCH=amd64 go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY)-darwin-amd64 ./cmd/kmp

darwin-arm64:
	GOOS=darwin GOARCH=arm64 go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY)-darwin-arm64 ./cmd/kmp

windows-amd64:
	GOOS=windows GOARCH=amd64 go build $(LDFLAGS) $(GOFLAGS) -o bin/$(BINARY)-windows-amd64.exe ./cmd/kmp
