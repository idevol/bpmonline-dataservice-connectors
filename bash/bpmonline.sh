#!/bin/bash

# https://github.com/idevol/bpmonline-dataservice-connectors

# bpm'online URL
BPMONLINE_URL='https://myproduct.bpmonline.com'

# bpm'online credentials
USER_NAME='Supervisor'
USER_PASSWORD='secret'

# bpm'online DataService URI's web service (API)
LOGIN_URI='/ServiceModel/AuthService.svc/Login'
SELECT_URI='/0/dataservice/json/SyncReply/SelectQuery'
INSERT_URI='/0/dataservice/json/reply/InsertQuery'
UPDATE_URI='/0/dataservice/json/reply/UpdateQuery'

# Files to be work
COOKIE_FILE_NAME='bpmonline.session.cookie'
QUERY_FILE_JSON='contact.query.json'

# bpm'online session token, leave empty
BPMCSRF=''

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color



echo -e "\n${RED}Login${NC}\n"
curl \
  --request POST \
  --cookie-jar "$COOKIE_FILE_NAME" \
  --header "Content-Type: application/json" \
  --data "{\"UserName\":\"$USER_NAME\",\"UserPassword\":\"$USER_PASSWORD\"}" \
  "$BPMONLINE_URL$LOGIN_URI"



# Get bpm'online session token on cookie jar file
while IFS='' read -r line || [[ -n "$line" ]]; do
  if echo "$line" | grep -q "BPMCSRF"; then
    echo -e "\n${GREEN}Found BPMCSRF${NC}"
    BPMCSRF=$(echo "$line" | awk '{print $7}')
  fi
done < "$COOKIE_FILE_NAME"
echo -e "${GREEN}BPMCSRF: $BPMCSRF${NC}\n"



echo -e "${RED}SelectQuery${NC}\n"
curl \
  --request POST \
  --cookie @"$COOKIE_FILE_NAME" \
  --header "BPMCSRF: $BPMCSRF" \
  --header "Content-Type: application/json" \
  --data @"$QUERY_FILE_JSON" \
  "$BPMONLINE_URL$SELECT_URI"

echo -e "\n"


