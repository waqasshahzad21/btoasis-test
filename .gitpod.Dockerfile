FROM gitpod/workspace-full
RUN sudo apt-get update  && sudo apt-get install -y   redis-server  && sudo rm -rf /var/lib/apt/lists/*

# Install custom tools, runtimes, etc.
# For example "bastet", a command-line tetris clone:
# RUN brew install bastet
#
# More information: https://www.gitpod.io/docs/config-docker/