#!/bin/bash

# bpm'online URL
BPMONLINE_URL='https://myproduct.bpmonline.com'

# bpm'online credentials
USER_NAME='Supervisor'
USER_PASSWORD='secret'

# bpm'online session token, leave empty
BPMCSRF=''

# Files to be work
COOKIE_FILE_NAME='bpmonline.session.cookie'
QUERY_FILE_JSON='contact.query.json'


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
  "$BPMONLINE_URL/ServiceModel/AuthService.svc/Login"



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
  "$BPMONLINE_URL/0/dataservice/json/SyncReply/SelectQuery"

echo -e "\n"


