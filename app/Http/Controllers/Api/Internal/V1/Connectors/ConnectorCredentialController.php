<?php

namespace App\Http\Controllers\Api\Internal\V1\Connectors;

use App\Enums\Workspaces\Permission;
use App\Exceptions\ConnectorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Connectors\StoreConnectorCredentialRequest;
use App\Http\Requests\Api\Internal\V1\Connectors\UpdateConnectorCredentialRequest;
use App\Http\Resources\Api\Internal\V1\Connectors\ConnectorCredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class ConnectorCredentialController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorView);

        $credentials = $workspace->connectorCredentials()
            ->visibleTo($request->user())
            ->with('connector')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['connector_credentials' => ConnectorCredentialResource::collection($credentials)]);
    }

    public function show(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorView);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector'))]);
    }

    public function store(StoreConnectorCredentialRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorManage);

        $connector = Connector::findOrFail($request->validated('connector_id'));

        if ($connector->isOAuth()) {
            throw new ConnectorException("Connector [{$connector->key}] is OAuth-only — use the OAuth connect flow instead of storing credential data directly.");
        }

        $credential = $workspace->connectorCredentials()->create([
            ...collect($request->validated())->except('is_default')->all(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->boolean('is_default')) {
            $credential->markAsDefault();
        }

        return ApiResponse::created(['connector_credential' => ConnectorCredentialResource::make($credential->load('connector'))], 'Connector credential created.');
    }

    public function update(UpdateConnectorCredentialRequest $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $connectorCredential->update($request->validated());

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector'))], 'Connector credential updated.');
    }

    public function destroy(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $connectorCredential->delete();

        return ApiResponse::noContent();
    }

    /**
     * Sets this credential as the one a node/agent gets when nothing pins
     * a `credential_id` — see `ResolvesConnectorCredential`.
     */
    public function setDefault(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $connectorCredential->markAsDefault();

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->fresh()->load('connector'))], 'Default connector credential set.');
    }

    /**
     * A personal credential belonging to someone else is treated as if it
     * doesn't exist — hidden, not merely forbidden, matching the "cannot
     * see or use" guarantee.
     */
    private function ensureVisible(Request $request, ConnectorCredential $connectorCredential): void
    {
        abort_unless($connectorCredential->isVisibleTo($request->user()), 404);
    }
}
