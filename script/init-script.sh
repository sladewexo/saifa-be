#!/bin/bash
# Run initialization commands

/usr/local/bin/setup-env.sh
/usr/local/bin/setup-migration.sh

# Execute the command specified by CMD
exec "$@"
