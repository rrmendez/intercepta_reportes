#!/usr/bin/env bash
#
# Deploy a produccion (cPanel) sin SSH:
#   1. git push origin main
#   2. UAPI VersionControl::update  -> el repo de cPanel hace pull desde GitHub
#   3. UAPI VersionControlDeployment::create -> ejecuta las tareas de .cpanel.yml
#   4. Espera y reporta el resultado del deploy
#
# Requiere el token de API de cPanel en ~/.config/intercepta/cpanel_token
# (crear en cPanel > Manage API Tokens).

set -euo pipefail

CPANEL_HOST="interceptauruguay.com.uy:2083"
CPANEL_USER="uinteruy"
TOKEN_FILE="${HOME}/.config/intercepta/cpanel_token"
REPO_ROOT="/home/uinteruy/repositories/intercepta_reportes"
BRANCH="main"

if [[ ! -f "$TOKEN_FILE" ]]; then
    echo "ERROR: no existe $TOKEN_FILE" >&2
    echo "Crea un token en cPanel (Manage API Tokens) y guardalo ahi con permisos 600." >&2
    exit 1
fi
TOKEN="$(tr -d '[:space:]' < "$TOKEN_FILE")"

uapi() {
    local module_func="$1"
    shift
    curl -sS -G -H "Authorization: cpanel ${CPANEL_USER}:${TOKEN}" \
        "https://${CPANEL_HOST}/execute/${module_func}" "$@"
}

check_uapi_ok() {
    local label="$1" json="$2"
    if ! python3 -c '
import json, sys
try:
    data = json.loads(sys.argv[2])
except ValueError:
    print(f"ERROR en {sys.argv[1]}: respuesta no es JSON: {sys.argv[2][:200]}", file=sys.stderr)
    sys.exit(1)
if not data.get("status"):
    errs = data.get("errors") or ["error desconocido"]
    print(f"ERROR en {sys.argv[1]}: " + "; ".join(str(e) for e in errs), file=sys.stderr)
    sys.exit(1)
' "$label" "$json"; then
        exit 1
    fi
}

echo "==> Pusheando ${BRANCH} a GitHub..."
git push origin "$BRANCH"

echo "==> Actualizando repo en cPanel (pull desde GitHub)..."
UPDATE_JSON="$(uapi "VersionControl/update" \
    --data-urlencode "repository_root=${REPO_ROOT}" \
    --data-urlencode "branch=${BRANCH}")"
check_uapi_ok "VersionControl::update" "$UPDATE_JSON"
python3 -c '
import json, sys
d = json.loads(sys.argv[1])["data"]["last_update"]
print("    HEAD en cPanel: %s - %s" % (d["identifier"][:9], d["message"].strip().splitlines()[0]))
' "$UPDATE_JSON" 2>/dev/null || true

echo "==> Lanzando deploy (.cpanel.yml)..."
DEPLOY_JSON="$(uapi "VersionControlDeployment/create" \
    --data-urlencode "repository_root=${REPO_ROOT}")"
check_uapi_ok "VersionControlDeployment::create" "$DEPLOY_JSON"
DEPLOY_ID="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["data"]["deploy_id"])' "$DEPLOY_JSON")"

echo "==> Esperando resultado del deploy #${DEPLOY_ID}..."
for _ in $(seq 1 60); do
    sleep 5
    RETRIEVE_JSON="$(uapi "VersionControlDeployment/retrieve")"
    STATE="$(python3 -c '
import json, sys
deploys = json.loads(sys.argv[1]).get("data") or []
target = next((d for d in deploys if d.get("deploy_id") == int(sys.argv[2])), None)
if target is None:
    print("unknown")
else:
    ts = target.get("timestamps") or {}
    if ts.get("succeeded"):
        print("succeeded")
    elif ts.get("failed"):
        print("failed")
    elif ts.get("canceled"):
        print("canceled")
    else:
        print("running")
' "$RETRIEVE_JSON" "$DEPLOY_ID")"
    case "$STATE" in
        succeeded)
            echo "==> Deploy OK ✅"
            exit 0
            ;;
        failed|canceled)
            echo "==> Deploy ${STATE} ❌ — revisa el log en cPanel (Git Version Control > Manage > Pull or Deploy)" >&2
            exit 1
            ;;
        *)
            printf '.'
            ;;
    esac
done
echo ""
echo "==> Timeout esperando el deploy; revisa el estado en cPanel." >&2
exit 1
