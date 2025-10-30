#!/bin/bash
echo "=== Recent Edit Logs ==="
tail -200 logs/debug.log | grep "Edit -" 
echo ""
echo "=== Recent Download Logs ==="
tail -200 logs/debug.log | grep "Download -"
echo ""
echo "=== Recent Template Source Logs ==="
tail -200 logs/debug.log | grep "Template source"
