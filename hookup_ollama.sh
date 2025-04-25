#! /bin/sh.
#use this to hookup ollama so that we can do local LLM assisted development.
nohup socat TCP-LISTEN:11434,reuseaddr,fork TCP:host.docker.internal:11434 &