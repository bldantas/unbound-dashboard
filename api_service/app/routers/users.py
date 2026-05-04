"""Endpoints /api/v1/users — espelho dos métodos user-mgmt em src/Auth.php."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, status
from pydantic import BaseModel, Field

from app.core.deps import require_admin, require_auth
from app.services import users_service

router = APIRouter(prefix="/api/v1/users", tags=["users"])


class CreateUserRequest(BaseModel):
    username: str = Field(min_length=1, max_length=50)
    password: str = Field(min_length=6, max_length=255)
    role: str = Field(default="viewer")
    email: str | None = None


class UpdateEmailRequest(BaseModel):
    email: str = Field(min_length=3, max_length=255)


@router.get("")
async def list_users(_: Annotated[dict, Depends(require_admin)]) -> list[dict]:
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
    _: Annotated[dict, Depends(require_admin)],
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
    # Auth model: permite admin OR self editar email
    is_admin = payload.get("role") == "admin"
    is_self = int(payload.get("sub", 0)) == user_id
    if not (is_admin or is_self):
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
    payload: Annotated[dict, Depends(require_admin)],
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


@router.delete("/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_user(
    user_id: Annotated[int, Path(ge=1)],
    payload: Annotated[dict, Depends(require_admin)],
) -> None:
    try:
        await users_service.delete_user(user_id, requesting_user_id=int(payload["sub"]))
    except users_service.CannotTargetSelf:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Você não pode excluir a si mesmo.",
        ) from None
    except users_service.UserNotFound:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado"
        ) from None
