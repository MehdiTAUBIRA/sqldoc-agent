<template>
    <AuthenticatedLayout>
      <template #header>
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-semibold text-gray-800">
            Administration
          </h2>
          <!--  Bouton aide -->
          <button
            @click="restartTutorial"
            class="fixed bottom-6 right-6 bg-indigo-600 text-white p-4 rounded-full shadow-lg hover:bg-indigo-700 hover:shadow-xl transition-all z-50 group"
            title="Show tutorial"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
              Need help ?
            </span>
          </button>
        </div>
      </template>
  
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <!--  Gestion des rôles et permissions -->
          <div id="roles-permissions-section" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-medium text-gray-900">Roles and permissions management</h3>
              <button
                @click="openCreateRoleModal"
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
              >
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create new role
              </button>
            </div>

            <div class="space-y-6">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="role in roles" :key="role.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ role.name }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                      {{ role.description }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex gap-2 flex-wrap">
                        <div v-for="permission in permissions" :key="permission.id" 
                          class="inline-flex items-center">
                          <label class="inline-flex items-center space-x-2">
                            <input 
                              type="checkbox"
                              :checked="hasPermission(role, permission)"
                              @change="togglePermission(role, permission)"
                              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm text-gray-700">{{ permission.name }}</span>
                          </label>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                      <button 
                        @click="saveRolePermissions(role)"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                      >
                        Save
                      </button>
                      <button 
                        @click="deleteRole(role)"
                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                        :disabled="['admin', 'user'].includes(role.name.toLowerCase())"
                        :class="{'opacity-50 cursor-not-allowed': ['admin', 'user'].includes(role.name.toLowerCase())}"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!--  Formulaire de création d'utilisateur -->
          <div id="create-user-section" class="mb-8 p-6 bg-white rounded-lg shadow">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Create a user</h4>
            <form @submit.prevent="createUser" class="space-y-4">
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input 
                    id="user-name-field"
                    v-model="newUser.name"
                    type="text" 
                    required
                    placeholder="Full name"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Email</label>
                  <input 
                    id="user-email-field"
                    v-model="newUser.email"
                    type="email" 
                    required
                    placeholder="email@example.com"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Password</label>
                  <input 
                    id="user-password-field"
                    v-model="newUser.password"
                    type="password" 
                    placeholder="min 8 characters"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Role</label>
                  <select 
                    id="user-role-field"
                    v-model="newUser.role_id"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                    <option value="">Select role</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">
                      {{ role.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="flex justify-end">
                <button 
                  id="create-user-button"
                  type="submit"
                  class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition"
                >
                  Create user
                </button>
              </div>
            </form>
          </div>
        
          <!--  Gestion des utilisateurs et accès aux projets -->
          <div id="users-management-section" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-6">User management and project access</h3>
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Name
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Role
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Project access
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(user, index) in users" :key="user.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ user.name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ user.email }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" style="min-width: 180px;">
                    <select 
                      :id="index === 0 ? 'user-role-dropdown' : undefined"
                      v-model="user.role_id"
                      @change="updateUserRole(user)"
                      class="mt-1 block w-full py-2 px-3 pr-6 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                      style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"
                    >
                      <option v-for="role in roles" :key="role.id" :value="role.id">
                        {{ role.name }}
                      </option>
                    </select>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500">
                    <div class="space-y-1">
                      <div v-if="user.project_accesses && user.project_accesses.length > 0" 
                           v-for="access in user.project_accesses" 
                           :key="access.id"
                           class="inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mr-1 mb-1">
                        {{ access.project.name }} 
                        <span class="ml-1 font-semibold">({{ access.access_level }})</span>
                        <button @click="revokeProjectAccess(user.id, access.project_id)" 
                                class="ml-1 text-red-600 hover:text-red-800">
                          <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                          </svg>
                        </button>
                      </div>
                      <div v-else class="text-gray-400 text-xs">
                        No project access
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 space-x-2">
                    <button
                      :id="index === 0 ? 'manage-access-button' : undefined"
                      @click="openProjectAccessModal(user)"
                      class="inline-flex items-center px-3 py-1 border border-transparent rounded-md text-xs text-white bg-blue-600 hover:bg-blue-700"
                    >
                      Manage Access
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Modal pour créer un rôle -->
      <div v-if="showCreateRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Create new role
            </h3>
            <button @click="closeCreateRoleModal" class="text-gray-400 hover:text-gray-500">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <form @submit.prevent="createRole" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Role name</label>
              <input 
                v-model="newRole.name"
                type="text" 
                required
                placeholder="e.g. Manager, Editor..."
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              >
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea 
                v-model="newRole.description"
                required
                rows="3"
                placeholder="Describe this role..."
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
              <div class="space-y-2 max-h-60 overflow-y-auto p-3 border border-gray-200 rounded-md">
                <div v-for="permission in permissions" :key="permission.id" class="flex items-center">
                  <input 
                    type="checkbox"
                    :id="`permission-${permission.id}`"
                    :value="permission.id"
                    v-model="newRole.permissions"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  >
                  <label :for="`permission-${permission.id}`" class="ml-2 text-sm text-gray-700">
                    {{ permission.name }}
                  </label>
                </div>
              </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button 
                type="button"
                @click="closeCreateRoleModal"
                class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400"
              >
                Cancel
              </button>
              <button 
                type="submit"
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
              >
                Create role
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal pour gérer l'accès aux projets -->
      <div v-if="showProjectAccessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Manage project access for {{ selectedUser?.name }}
            </h3>
            <button @click="closeProjectAccessModal" class="text-gray-400 hover:text-gray-500">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <div class="mb-2 text-sm text-gray-600">
            Projects available: {{ availableProjects.length }}
            <span v-if="loadingProjects" class="text-blue-600">(Loading...)</span>
          </div>
          
          <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h4 class="text-md font-medium text-gray-900 mb-3">Grant new project access</h4>
            <form @submit.prevent="grantProjectAccess" class="space-y-3">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium text-gray-700">projects</label>
                  <select 
                    v-model="newProjectAccess.project_ids"
                    multiple
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                    <option 
                      v-for="project in availableProjects" 
                      :key="project.id" 
                      :value="project.id"
                    >
                      {{ project.display_name || project.name }}
                    </option>
                  </select>
                  <div v-if="availableProjects.length === 0" class="mt-1 text-sm text-red-600">
                    No projects available. Check console for errors.
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Access level</label>
                  <select 
                    v-model="newProjectAccess.access_level"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  >
                    <option value="read">Read only</option>
                    <option value="write">Read/Write</option>
                    <option value="admin">Full admin</option>
                  </select>
                </div>
              </div>

              <div class="flex justify-end">
                <button 
                  type="submit"
                  :disabled="!newProjectAccess.project_ids.length === 0 || loadingProjects"
                  class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50"
                >
                  Grant access
                </button>
              </div>
            </form>
          </div>

          <div>
            <h4 class="text-md font-medium text-gray-900 mb-3">Current project accesses</h4>
            <div v-if="currentUserAccesses.length === 0" class="text-gray-500 text-sm">
              No project access granted yet.
            </div>
            <div v-else class="space-y-2">
              <div v-for="access in currentUserAccesses" 
                   :key="access.id"
                   class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <div class="font-medium text-gray-900">{{ access.project_name }}</div>
                  <div class="text-sm text-gray-500">
                    Owner: {{ access.project_owner }} | 
                    Access: <span class="font-medium">{{ access.access_level }}</span> | 
                    Granted: {{ access.granted_at }}
                  </div>
                </div>
                <button 
                  @click="revokeProjectAccess(selectedUser.id, access.project_id)"
                  class="text-red-600 hover:text-red-800"
                  title="Revoke access"
                >
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
  
<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from '@/Composables/useToast'
import { useDriver } from '@/Composables/useDriver.js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const { success, error: showError, warning, info, confirmToast } = useToast()
const { showAdminGuide } = useDriver()

const props = defineProps({
  users: Array,
  roles: Array,
  permissions: Array,
  projects: Array
})


const newUser = ref({ name: '', email: '', password: '', role_id: '' })

const showCreateRoleModal = ref(false)
const newRole = ref({ name: '', description: '', permissions: [] })

const showProjectAccessModal = ref(false)
const selectedUser = ref(null)
const currentUserAccesses = ref([])

const availableProjects = ref([])
const newProjectAccess = ref({ project_ids: [], access_level: 'read' })


const restartTutorial = () => {
  localStorage.removeItem('admin_tutorial_shown')
  showAdminGuide()
}

onMounted(() => {
  if (!localStorage.getItem('admin_tutorial_shown')) {
    setTimeout(() => {
      showAdminGuide()
      localStorage.setItem('admin_tutorial_shown', 'true')
    }, 1000)
  }

  // Charger projets depuis props
  if (props.projects?.length) {
    availableProjects.value = props.projects.map(p => ({
      id: p.id,
      name: p.name,
      display_name: `${p.name} (${p.user?.name ?? 'Unknown'})`
    }))
  }
})


const hasPermission = (role, permission) => {
  return role.permissions?.some(p => p.id === permission.id) || false
}

const togglePermission = (role, permission) => {
  if (!role.permissions) {
    role.permissions = []
  }
  
  const index = role.permissions.findIndex(p => p.id === permission.id)
  if (index > -1) {
    role.permissions.splice(index, 1)
  } else {
    role.permissions.push(permission)
  }
}


const openCreateRoleModal = () => {
  showCreateRoleModal.value = true
  newRole.value = { name: '', description: '', permissions: [] }
}

const closeCreateRoleModal = () => {
  showCreateRoleModal.value = false
}

const createRole = () => {
  router.post('/admin/roles', newRole.value, {
    onSuccess: () => {
      success('Role created successfully!')
      closeCreateRoleModal()
    },
    onError: errors => showError(Object.values(errors).flat().join('\n'))
  })
}

const deleteRole = role => {
  if (['admin', 'user'].includes(role.name.toLowerCase())) {
    return warning('Cannot delete default roles')
  }

  confirmToast({
    message: `Delete role "${role.name}"?`,
    onConfirm: () => {
      router.delete(`/admin/roles/${role.id}`, {
        onSuccess: () => success('Role deleted!'),
        onError: () => showError('Error deleting role')
      })
    }
  })
}

const saveRolePermissions = role => {
  router.put(`/admin/roles/${role.id}/permissions`, {
    permissions: role.permissions.map(p => p.id)
  }, {
    onSuccess: () => info('Permissions updated'),
    onError: () => showError('Error updating permissions')
  })
}


const createUser = () => {
  router.post('/admin/users', newUser.value, {
    onSuccess: () => {
      success('User created!')
      newUser.value = { name: '', email: '', password: '', role_id: '' }
    },
    onError: errors => showError(Object.values(errors).flat().join('\n'))
  })
}

const updateUserRole = user => {
  router.put(`/admin/users/${user.id}/role`, { role_id: user.role_id }, {
    onSuccess: () => info('Role updated'),
    onError: () => showError('Error updating role')
  })
}


const openProjectAccessModal = user => {
  selectedUser.value = user
  showProjectAccessModal.value = true

  currentUserAccesses.value =
    user.project_accesses?.map(a => ({
      id: a.id,
      project_id: a.project.id,
      project_name: a.project.name,
      project_owner: a.project.user?.name || 'Unknown',
      access_level: a.access_level,
      granted_at: new Date(a.created_at).toLocaleDateString('fr-FR')
    })) || []
}

const closeProjectAccessModal = () => {
  showProjectAccessModal.value = false
  selectedUser.value = null
  currentUserAccesses.value = []
  newProjectAccess.value = { project_ids: [], access_level: 'read' }
}

const grantProjectAccess = () => {
  if (!newProjectAccess.value.project_ids.length) {
    return showError('Select at least one project')
  }

  router.post('/admin/project-access/grant', {
    user_id: selectedUser.value.id,
    project_ids: newProjectAccess.value.project_ids,
    access_level: newProjectAccess.value.access_level
  }, {
    onSuccess: () => {
      success('Access granted!')
      closeProjectAccessModal()
    },
    onError: errors => showError(Object.values(errors).flat().join('\n'))
  })
}

const revokeProjectAccess = (userId, projectId) => {
  confirmToast({
    message: 'Revoke this project access?',
    onConfirm: () => {
      router.post('/admin/project-access/revoke', {
        user_id: userId,
        project_ids: [projectId]
      }, {
        onSuccess: () => {
          success('Access revoked')
          closeProjectAccessModal()
        },
        onError: () => showError('Error revoking access')
      })
    }
  })
}
</script>