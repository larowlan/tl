## Command line time-logger

* Entries stored in sqlite in your home directory
* Sends complete entries to redmine

## Installation

```bash
# Get the phar
wget http://larowlan.github.io/tl/tl.phar
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
# send entries to redmine
tl send
```
Rinse and repeat the next day
