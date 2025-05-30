#!/bin/bash
# set_display.sh - Helper to set DISPLAY for X11 forwarding to macOS host (XQuartz)
# Falls back to virtual display if host X11 is not available

# Default to Docker Desktop's special DNS name for host
# Use environment variable if set, otherwise fallback to default
HOST_DISPLAY="${DISPLAY:-host.docker.internal:0}"

# Allow override via argument
if [ -n "$1" ]; then
  HOST_DISPLAY="$1"
fi

# Install required X11 utilities if missing
install_x11_utils() {
    if ! command -v xdpyinfo >/dev/null 2>&1; then
        echo "Installing X11 utilities..."
        sudo apt-get update >/dev/null 2>&1 && sudo apt-get install -y x11-utils >/dev/null 2>&1
    fi
}

# Function to test X11 connection
test_display() {
    # Install utils if needed
    install_x11_utils
    
    # Test if we can connect to the display
    if command -v xdpyinfo >/dev/null 2>&1; then
        DISPLAY="$1" timeout 3 xdpyinfo >/dev/null 2>&1
        return $?
    else
        # Fallback test using xeyes
        DISPLAY="$1" timeout 3 xeyes >/dev/null 2>&1 &
        local pid=$!
        sleep 1
        if kill -0 $pid 2>/dev/null; then
            kill $pid 2>/dev/null
            return 0
        else
            return 1
        fi
    fi
}

# Try to connect to host X11 first
echo "Testing X11 connection to $HOST_DISPLAY..."
if test_display "$HOST_DISPLAY"; then
    export DISPLAY="$HOST_DISPLAY"
    echo "✓ Connected to host X11 display: $DISPLAY"
    echo "GUI apps (like Playwright headed mode) will show on your macOS desktop."
else
    echo "✗ Cannot connect to host X11 display. Starting virtual display..."
    
    # Install xvfb if not present
    if ! command -v xvfb-run >/dev/null 2>&1; then
        echo "Installing xvfb..."
        sudo apt-get update && sudo apt-get install -y xvfb
    fi
    
    # Start virtual display
    export DISPLAY=":99"
    if ! pgrep Xvfb >/dev/null; then
        Xvfb :99 -screen 0 1024x768x24 >/dev/null 2>&1 &
        sleep 2
    fi
    
    if test_display "$DISPLAY"; then
        echo "✓ Virtual X11 display started: $DISPLAY"
        echo "GUI apps will run in virtual display (no visual output on your desktop)."
        echo "To see GUI output on your Mac:"
        echo "  1. Start XQuartz and enable 'Allow connections from network clients'"
        echo "  2. Run: xhost + in your Mac terminal"
        echo "  3. Run this script again"
    else
        echo "✗ Failed to start virtual display"
        exit 1
    fi
fi

echo ""
echo "Current DISPLAY: $DISPLAY"
echo "Test with: xeyes"
echo ""
echo "For Playwright:"
echo "  - Headed mode: npm run test:ui:headed"
echo "  - Debug mode: npm run test:ui:debug"
