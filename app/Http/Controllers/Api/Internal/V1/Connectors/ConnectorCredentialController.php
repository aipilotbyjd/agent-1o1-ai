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

class ConnectorCredentialController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorView);

        $credentials = $workspace->connectorCredentials()->with('connector')->orderBy('name')->get();

        return ApiResponse::success(['connector_credentials' => ConnectorCredentialResource::collection($credentials)]);
    }

    public function show(Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorView);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);

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
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['connector_credential' => ConnectorCredentialResource::make($credential->load('connector'))], 'Connector credential created.');
    }

    public function update(UpdateConnectorCredentialRequest $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);

        $connectorCredential->update($request->validated());

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector'))], 'Connector credential updated.');
    }

    public function destroy(Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);

        $connectorCredential->delete();

        return ApiResponse::noContent();
    }
}
