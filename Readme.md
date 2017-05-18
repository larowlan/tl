![Build status](https://travis-ci.org/larowlan/tl.svg?branch=master)
## Command line time-logger

* Entries stored in sqlite in your home directory
* Sends complete entries to redmine

## Requirements

* PHP 5.4+ (short array syntax)
* php5-sqlite

## Installation

### Linux or homebrew w/ wget

```bash
# Get the phar
wget http://larowlan.github.io/tl/tl.phar
```

### OSX

```bash
# Get the phar
curl -O http://larowlan.github.io/tl/tl.phar
```

## Setup

```bash
# Copy to /usr/local/bin/ or somewhere else in your $PATH
sudo cp tl.phar /usr/local/bin/tl
# Set execute flag
sudo chmod 775 /usr/local/bin/tl
```

## Setup the db

```bash
tl install
```

## Configure your redmine details

```bash
tl configure
# follow the prompts
```

## Updating

```bash
tl self-update
```

## Usage

Assuming you have two redmine tickets number 3546 and 4791.
```bash
# start work on 3546
tl start 3546
# stop work
tl stop
# continue work
tl start 3546
# stop working on 3546, work on 4791
tl start 4791
# see how long on open ticket
tl open
# stop work
tl stop
# see total unsent
tl total
# edit entry
tl edit [slot id] [hours]
# review entries
tl review
# comment on entries before sending
tl comment
# tag entries before sending
tl tag
# delete entry before sending
tl del 3546
# send entries to redmine
tl send
# show all available commands
tl list
# show help for any command
tl help [command]
```
Rinse and repeat the next day

## Docker

### Installation

**Build this image**

```bash
docker build -t larowlan/tl .
```

NOTE: Until we have builds on the Docker Hub.

**Bash alias**

The following will make your `tl` cli command appear like it's running native, but it's not! it's running in
a container!

Add the following to your `~/.bashrc` file.

```bash
alias tl='docker run -v $HOME:/root -it larowlan/tl'
```

**Run**

You can now follow the same steps as above for installation.

eg.

```bash
tl install
```
