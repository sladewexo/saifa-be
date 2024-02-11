#!/bin/bash
# Run initialization commands

/usr/local/bin/setup-migration.sh
/usr/local/bin/setup-env.sh

# Execute the command specified by CMD
exec "$@"
