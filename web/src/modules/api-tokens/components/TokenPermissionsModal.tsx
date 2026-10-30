import { FormProvider, useForm } from 'react-hook-form'
import { Button, Modal } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { Permission } from '@/shared/constants/permissions'
import type { ApiToken } from '@/shared/types/models'
import { PermissionsField } from '@/modules/roles/components/PermissionsField'
import { useUpdateApiToken } from '../hooks/useApiTokens'

interface TokenPermissionsModalProps {
  token: ApiToken
  onClose: () => void
}

interface FormValues {
  permissions: Permission[]
}

/** Montado com `key={token.id}`: o estado inicial vem sempre do token aberto. */
export function TokenPermissionsModal({ token, onClose }: TokenPermissionsModalProps) {
  const updateToken = useUpdateApiToken()
  const form = useForm<FormValues>({
    defaultValues: { permissions: (token.permissions ?? []) as Permission[] },
  })

  const save = async (values: FormValues) => {
    try {
      await updateToken.mutateAsync({
        id: token.id,
        permissions: values.permissions.length > 0 ? values.permissions : null,
      })
      onClose()
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={`Escopos de ${token.name}`}
      description="O token continua o mesmo; só mudam as ações permitidas. Sem nenhuma permissão selecionada, o token terá acesso irrestrito."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={form.handleSubmit(save)} loading={updateToken.isPending}>
            Salvar
          </Button>
        </>
      }
    >
      <FormProvider {...form}>
        <PermissionsField name="permissions" />
      </FormProvider>
    </Modal>
  )
}
