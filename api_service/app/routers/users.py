"""Endpoints /api/v1/users — espelho dos métodos user-mgmt em src/Auth.php."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, Request, status
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from app.core.deps import require_auth, require_capability
from app.services import approval_service, users_service


async def _approval_handler_user_delete(payload: dict) -> dict:
    target_id = int(payload.get("user_id", 0))
    requesting_id = int(payload.get("requesting_user_id", 0))
    if target_id < 1 or requesting_id < 1:
        return {"ok": False, "error": "user_id/requesting_user_id ausentes"}
    try:
        await users_service.delete_user(target_id, requesting_user_id=requesting_id)
    except users_service.CannotTargetSelf:
        return {"ok": False, "error": "self-target"}
    except users_service.UserNotFound:
        return {"ok": False, "error": "user not found"}
    return {"ok": True, "deleted_user_id": target_id}


approval_service.register_action_handler("users.delete", _approval_handler_user_delete)

router = APIRouter(prefix="/api/v1/users", tags=["users"])


class CreateUserRequest(BaseModel):
    username: str = Field(min_length=1, max_length=50)
    password: str = Field(min_length=6, max_length=255)
    role: str = Field(default="viewer")
    email: str | None = None


class UpdateEmailRequest(BaseModel):
    email: str = Field(min_length=3, max_length=255)


class UpdateRoleRequest(BaseModel):
    role: str = Field(min_length=1, max_length=20)


@router.get("")
async def list_users(_: Annotated[dict, Depends(require_capability("users.read"))]) -> list[dict]:
    return await users_service.list_all()


@router.get("/exists")
async def users_exist() -> dict:
    """
    Pública — usada por setup.php pra decidir se mostra wizard ou login.
    Não exige auth porque pré-instalação não tem como autenticar.
    """
    return {"exists": await users_service.has_any_users()}


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_user(
    body: CreateUserRequest,
    _: Annotated[dict, Depends(require_capability("users.manage"))],
) -> dict:
    try:
        new_id = await users_service.create(
            username=body.username,
            password=body.password,
            role=body.role,
            email=body.email,
        )
    except users_service.WeakPassword:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Senha deve ter no mínimo 6 caracteres.",
        ) from None
    except users_service.UsernameAlreadyExists:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Nome de usuário ou email indisponível.",
        ) from None
    return {"id": new_id}


@router.put("/{user_id}/email", status_code=status.HTTP_204_NO_CONTENT)
async def update_email(
    user_id: Annotated[int, Path(ge=1)],
    body: UpdateEmailRequest,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    # Auth model: permite users.manage OR self editar email
    from app.core.rbac import can
    is_manager = can(payload.get("role"), "users.manage")
    is_self = int(payload.get("sub", 0)) == user_id
    if not (is_manager or is_self):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Acesso negado")
    try:
        await users_service.update_email(user_id, body.email)
    except users_service.EmailAlreadyExists:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Este email já está em uso.",
        ) from None


@router.put("/{user_id}/active", status_code=status.HTTP_204_NO_CONTENT)
async def toggle_active(
    user_id: Annotated[int, Path(ge=1)],
    payload: Annotated[dict, Depends(require_capability("users.manage"))],
) -> None:
    try:
        await users_service.toggle_active(user_id, requesting_user_id=int(payload["sub"]))
    except users_service.CannotTargetSelf:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Você não pode desativar a si mesmo.",
        ) from None
    except users_service.UserNotFound:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado"
        ) from None


@router.delete("/{user_id}", response_model=None)
async def delete_user(
    user_id: Annotated[int, Path(ge=1)],
    request: Request,
    payload: Annotated[dict, Depends(require_capability("users.manage"))],
):
    requesting_id = int(payload["sub"])
    ip = request.client.host if request.client else None
    try:
        await approval_service.enforce_approval(
            user=payload, request_ip=ip,
            action="users.delete",
            description=f"Excluir usuário id={user_id}",
            payload={"user_id": user_id, "requesting_user_id": requesting_id},
        )
    except approval_service.ApprovalRequired as exc:
        return JSONResponse(
            {"approval_pending": True, "request_id": exc.request_id,
             "message": "Aguardando aprovação de outro admin em /approvals.php"},
            status_code=202,
        )
    try:
        await users_service.delete_user(user_id, requesting_user_id=requesting_id)
    except users_service.CannotTargetSelf:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Você não pode excluir a si mesmo.",
        ) from None
    except users_service.UserNotFound:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado"
        ) from None
    return JSONResponse(content=None, status_code=status.HTTP_204_NO_CONTENT)


@router.put("/{user_id}/role", status_code=status.HTTP_204_NO_CONTENT)
async def update_role(
    user_id: Annotated[int, Path(ge=1)],
    body: UpdateRoleRequest,
    payload: Annotated[dict, Depends(require_capability("users.manage"))],
) -> None:
    try:
        await users_service.update_role(
            user_id, body.role, requesting_user_id=int(payload["sub"])
        )
    except users_service.InvalidRole:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Role inválido. Valores aceitos: admin, readonly_admin, operator, viewer.",
        ) from None
    except users_service.CannotTargetSelf:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Você não pode mudar seu próprio role. Peça pra outro admin.",
        ) from None
    except users_service.UserNotFound:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado"
        ) from None


@router.post("/{user_id}/password-reset")
async def admin_reset_password(
    user_id: Annotated[int, Path(ge=1)],
    payload: Annotated[dict, Depends(require_capability("users.manage"))],
) -> dict:
    """
    Admin gera senha temporária aleatória para o user. Senha é retornada
    UMA VEZ na resposta (texto plano) — admin deve entregar manualmente
    ao usuário e este deve trocar no primeiro acesso.
    """
    try:
        new_pass = await users_service.admin_reset_password(
            user_id, requesting_user_id=int(payload["sub"])
        )
    except users_service.UserNotFound:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado"
        ) from None
    return {"temporary_password": new_pass}
