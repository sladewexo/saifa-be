#!/bin/bash

# step 1 make env
ENV_FILE="/var/www/html/.env"
EXAMPLE_ENV_FILE="/var/www/html/.example_env"
if [ -f "$ENV_FILE" ]; then
    echo "Existing .env file found. Removing..."
    rm -f "$ENV_FILE"
fi
if [ -f "$EXAMPLE_ENV_FILE" ]; then
    echo "Copying .example_env to .env..."
    cp "$EXAMPLE_ENV_FILE" "$ENV_FILE"
else
    echo "Template .example_env file not found. Cannot create .env file."
    exit 1
fi
echo ".env file is ready."


# step 2 read macaroon and make it to hex and set app password
if [ -z "$BITCOIN_NETWORK" ]; then
    export BITCOIN_NETWORK="testnet"
else
    echo "BITCOIN_NETWORK is already set to '$BITCOIN_NETWORK'."
fi

MACAROON_PATH="/lnd/data/chain/bitcoin/$BITCOIN_NETWORK"
MACAROON_PATH_FULL="/var/www/html$MACAROON_PATH/admin.macaroon"
if [ ! -f "$MACAROON_PATH_FULL" ]; then
    echo "File MACAROON_PATH_FULL  $MACAROON_PATH_FULL does not exist."
fi
NEW_MACAROON_VALUE=$(xxd -p -c9999 "$MACAROON_PATH_FULL") # Encode the LND macaroon to hex
#echo "NEW_MACAROON_VALUE  is is is ->>>>> $NEW_MACAROON_VALUE"


# step 3 update .env file with NEW_MACAROON_VALUE config
if [ -f "$ENV_FILE" ]; then
    awk -v newvalue="$NEW_MACAROON_VALUE" 'BEGIN{FS=OFS="="} $1=="LND_MARCAROON" {$2=newvalue} 1' "$ENV_FILE" > tmpfile && mv tmpfile "$ENV_FILE"
    echo "LND_MACAROON updated in .env file."
else
    echo ".env file does not exist."
fi