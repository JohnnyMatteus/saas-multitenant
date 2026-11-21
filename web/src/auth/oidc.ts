import { UserManager, WebStorageStateStore, type User } from 'oidc-client-ts'

const origin = typeof window === 'undefined' ? 'http://localhost:5173' : window.location.origin

// sessionStorage: o token morre com a aba e não fica para o próximo usuário do balcão.
export const userManager = new UserManager({
  authority: import.meta.env.VITE_OIDC_AUTHORITY ?? 'http://localhost:8080/realms/estudios',
  client_id: import.meta.env.VITE_OIDC_CLIENT_ID ?? 'studio-web',
  redirect_uri: `${origin}/callback`,
  post_logout_redirect_uri: `${origin}/`,
  response_type: 'code',
  scope: 'openid profile email',
  automaticSilentRenew: true,
  userStore: typeof window === 'undefined' ? undefined : new WebStorageStateStore({ store: window.sessionStorage }),
})

export async function currentUser(): Promise<User | null> {
  const user = await userManager.getUser()
  return user && !user.expired ? user : null
}

export async function accessToken(): Promise<string | null> {
  return (await currentUser())?.access_token ?? null
}

export function login(returnTo = '/') {
  return userManager.signinRedirect({ state: { returnTo } })
}

export async function logout() {
  const user = await userManager.getUser()
  await userManager.removeUser()
  return userManager.signoutRedirect({ id_token_hint: user?.id_token })
}
