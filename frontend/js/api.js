// API Configuration
const API_BASE_URL = 'http://localhost:8000/api'; // Update this to your backend URL

// Helper function to get stored token
function getToken() {
    return localStorage.getItem('auth_token');
}

// Helper function to set token
function setToken(token) {
    localStorage.setItem('auth_token', token);
}

// Helper function to remove token
function removeToken() {
    localStorage.removeItem('auth_token');
}

// Helper function to make API requests
async function apiRequest(endpoint, options = {}) {
    const token = getToken();
    const url = `${API_BASE_URL}${endpoint}`;
    
    const config = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        // Only include credentials if explicitly needed (for session-based auth)
        // For token-based auth (Laravel Sanctum), we don't need this
        ...options,
    };

    // Add token to headers if available
    // Don't warn for login/signup endpoints as they don't need tokens
    if (token && !endpoint.includes('/login') && !endpoint.includes('/signup') && !endpoint.includes('/forgot-password') && !endpoint.includes('/reset-password')) {
        config.headers['Authorization'] = `Bearer ${token}`;
        console.log('Adding Authorization header with token');
    } else if (!token && !endpoint.includes('/login') && !endpoint.includes('/signup') && !endpoint.includes('/forgot-password') && !endpoint.includes('/reset-password')) {
        console.warn('No token available for request to:', endpoint);
    }

    // Add body if provided
    if (options.body && typeof options.body === 'object') {
        config.body = JSON.stringify(options.body);
    }

    try {
        const response = await fetch(url, config);
        
        // Handle non-JSON responses
        let data;
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Server error: ${response.status} ${text}`);
        }

        if (!response.ok) {
            // Include status code in error for better handling
            const error = new Error(data.message || 'An error occurred');
            error.status = response.status;
            error.data = data; // Include full error response
            error.error = data; // For compatibility
            throw error;
        }

        return data;
    } catch (error) {
        // Preserve status code if available
        if (error.status) {
            throw error;
        }
        // For network errors, add a flag
        error.isNetworkError = true;
        throw error;
    }
}

// Authentication Functions

/**
 * Register a new user
 * @param {Object} userData - { name, email, password, password_confirmation, major?, university? }
 * @returns {Promise<Object>} - { user, token }
 */
async function register(userData) {
    try {
        const response = await apiRequest('/signup', {
            method: 'POST',
            body: userData,
        });

        console.log('Register response:', response);
        
        // Check different possible token field names
        const token = response.token || response.access_token || response.data?.token || response.data?.access_token;
        
        if (token) {
            setToken(token);
            console.log('✅ Token stored after registration');
        } else {
            console.warn('No token in register response:', response);
        }

        return response;
    } catch (error) {
        console.error('Register error:', error);
        throw error;
    }
}

/**
 * Login user
 * @param {string} email - User email
 * @param {string} password - User password
 * @returns {Promise<Object>} - { user, token }
 */
async function login(email, password) {
    try {
        const response = await apiRequest('/login', {
            method: 'POST',
            body: { email, password },
        });

        console.log('Login response:', response);
        console.log('Response keys:', Object.keys(response));
        console.log('Response type:', typeof response);
        
        // Check different possible token field names
        let token = response.token || response.access_token || response.data?.token || response.data?.access_token;
        
        // If response is nested in 'data' or 'user' object
        if (!token && response.data) {
            token = response.data.token || response.data.access_token;
        }
        if (!token && response.user) {
            token = response.user.token || response.user.access_token;
        }
        
        if (token) {
            setToken(token);
            console.log('✅ Token stored successfully');
            console.log('Token exists check:', !!getToken());
            console.log('Token length:', token.length);
            console.log('Token preview:', token.substring(0, 20) + '...');
        } else {
            console.error('❌ No token found in response');
            console.error('Full response:', JSON.stringify(response, null, 2));
            
            // Check if maybe it's session-based (no token needed)
            if (response.user || response.message) {
                console.warn('⚠️ Response has user/message but no token - might be session-based auth');
                // For session-based, we might not need a token
                // Set a dummy token to mark as authenticated
                setToken('session-based');
            }
        }

        return response;
    } catch (error) {
        console.error('Login error:', error);
        throw error;
    }
}

/**
 * Logout user
 * @returns {Promise<Object>}
 */
async function logout() {
    try {
        await apiRequest('/logout', {
            method: 'POST',
        });
        
        removeToken();
        return { success: true };
    } catch (error) {
        // Even if API call fails, remove token locally
        removeToken();
        throw error;
    }
}

/**
 * Get current user info
 * @returns {Promise<Object>} - User object
 */
async function getCurrentUser() {
    try {
        const token = getToken();
        const config = {
            method: 'GET',
        };
        
        // Only add Authorization header if we have a real token (not session-based)
        if (token && token !== 'session-based') {
            // Token will be added automatically by apiRequest
        } else {
            console.log('Making request without Authorization header (session-based)');
        }
        
        const response = await apiRequest('/me', config);
        return response;
    } catch (error) {
        console.error('getCurrentUser error:', error);
        throw error;
    }
}

/**
 * Request password reset
 * @param {string} email - User email
 * @returns {Promise<Object>}
 */
async function forgotPassword(email) {
    try {
        const response = await apiRequest('/forgot-password', {
            method: 'POST',
            body: { email },
        });
        return response;
    } catch (error) {
        throw error;
    }
}

/**
 * Reset password with token
 * @param {string} token - Reset token
 * @param {string} email - User email
 * @param {string} password - New password
 * @param {string} password_confirmation - Password confirmation
 * @returns {Promise<Object>}
 */
async function resetPassword(token, email, password, password_confirmation) {
    try {
        const response = await apiRequest('/reset-password', {
            method: 'POST',
            body: { token, email, password, password_confirmation },
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// Semesters API
async function getSemesters() {
    try {
        const response = await apiRequest('/semesters', {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function getSemester(id) {
    try {
        const response = await apiRequest(`/semesters/${id}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function createSemester(data) {
    try {
        const response = await apiRequest('/semesters', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function updateSemester(id, data) {
    try {
        const response = await apiRequest(`/semesters/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function deleteSemester(id) {
    try {
        const response = await apiRequest(`/semesters/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// Courses API
async function getCourses(semesterId) {
    try {
        const response = await apiRequest(`/semesters/${semesterId}/courses`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function createCourse(semesterId, data) {
    try {
        const response = await apiRequest(`/semesters/${semesterId}/courses`, {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function updateCourse(courseId, data) {
    try {
        const response = await apiRequest(`/courses/${courseId}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function deleteCourse(courseId) {
    try {
        const response = await apiRequest(`/courses/${courseId}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// Assessments API
async function getAssessments(courseId) {
    try {
        const response = await apiRequest(`/courses/${courseId}/assessments`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function getAssessment(assessmentId) {
    try {
        const response = await apiRequest(`/assessments/${assessmentId}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function createAssessment(courseId, data) {
    try {
        const response = await apiRequest(`/courses/${courseId}/assessments`, {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function updateAssessment(assessmentId, data) {
    try {
        const response = await apiRequest(`/assessments/${assessmentId}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function deleteAssessment(assessmentId) {
    try {
        const response = await apiRequest(`/assessments/${assessmentId}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// Events API
async function getEvents(params = {}) {
    try {
        const query = new URLSearchParams();
        if (params.start_date) query.append('start_date', params.start_date);
        if (params.end_date) query.append('end_date', params.end_date);
        const qs = query.toString() ? `?${query.toString()}` : '';
        
        const response = await apiRequest(`/events${qs}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function getEvent(id) {
    try {
        const response = await apiRequest(`/events/${id}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function createEvent(data) {
    try {
        const response = await apiRequest('/events', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function updateEvent(id, data) {
    try {
        const response = await apiRequest(`/events/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function deleteEvent(id) {
    try {
        const response = await apiRequest(`/events/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// Notes API
async function getNotes(courseId) {
    try {
        const response = await apiRequest(`/courses/${courseId}/notes`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function getNote(noteId) {
    try {
        const response = await apiRequest(`/notes/${noteId}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function createNote(courseId, data) {
    try {
        const response = await apiRequest(`/courses/${courseId}/notes`, {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function updateNote(noteId, data) {
    try {
        const response = await apiRequest(`/notes/${noteId}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function deleteNote(noteId) {
    try {
        const response = await apiRequest(`/notes/${noteId}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function pinNote(noteId) {
    try {
        const response = await apiRequest(`/notes/${noteId}/pin`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function favoriteNote(noteId) {
    try {
        const response = await apiRequest(`/notes/${noteId}/favorite`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

async function searchNotes(query) {
    try {
        const response = await apiRequest(`/notes/search?q=${encodeURIComponent(query)}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        throw error;
    }
}

// File Attachments API
async function uploadAttachment(file, attachableType = null, attachableId = null) {
    try {
        const formData = new FormData();
        // Laravel typically expects 'file' as the field name
        formData.append('file', file);
        
        // Add attachable info if provided
        if (attachableType) {
            formData.append('attachable_type', attachableType);
        }
        if (attachableId) {
            formData.append('attachable_id', attachableId);
        }
        
        const token = getToken();
        if (!token) {
            throw new Error('No authentication token found');
        }
        
        console.log('Uploading file:', {
            name: file.name,
            size: file.size,
            type: file.type,
            attachableType: attachableType,
            attachableId: attachableId
        });
        
        const response = await fetch(`${API_BASE_URL}/attachments/upload`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                // Don't set Content-Type for FormData - browser will set it automatically with boundary
            },
            body: formData,
        });
        
        if (!response.ok) {
            let errorMessage = 'Upload failed';
            try {
                const errorData = await response.json();
                console.error('Upload error response:', errorData);
                
                // Handle Laravel validation errors
                if (errorData.errors) {
                    const errorMessages = Object.values(errorData.errors).flat();
                    errorMessage = errorMessages.join(', ') || errorData.message || 'Validation failed';
                } else if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                const text = await response.text();
                console.error('Error response text:', text);
                errorMessage = `HTTP error! status: ${response.status}`;
            }
            
            const error = new Error(errorMessage);
            error.status = response.status;
            throw error;
        }
        
        const result = await response.json();
        console.log('Upload successful:', result);
        return result;
    } catch (error) {
        console.error('Upload attachment error:', error);
        throw error;
    }
}

async function downloadAttachment(attachmentId) {
    try {
        const token = getToken();
        if (!token) {
            throw new Error('No authentication token found');
        }
        
        console.log('Downloading attachment:', attachmentId);
        
        const response = await fetch(`${API_BASE_URL}/attachments/${attachmentId}/download`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
            },
        });
        
        if (!response.ok) {
            let errorMessage = 'Download failed';
            try {
                const errorData = await response.json();
                errorMessage = errorData.message || `HTTP error! status: ${response.status}`;
            } catch (e) {
                errorMessage = `HTTP error! status: ${response.status}`;
            }
            throw new Error(errorMessage);
        }
        
        // Get filename from Content-Disposition header
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = 'download';
        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (filenameMatch && filenameMatch[1]) {
                filename = filenameMatch[1].replace(/['"]/g, '');
                // Decode URI if needed
                try {
                    filename = decodeURIComponent(filename);
                } catch (e) {
                    // Keep original if decode fails
                }
            }
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        console.log('Download successful:', filename);
    } catch (error) {
        console.error('Download attachment error:', error);
        throw error;
    }
}

async function deleteAttachment(attachmentId) {
    try {
        console.log('Deleting attachment:', attachmentId);
        const response = await apiRequest(`/attachments/${attachmentId}`, {
            method: 'DELETE',
        });
        console.log('Delete successful:', response);
        return response;
    } catch (error) {
        console.error('Delete attachment error:', error);
        throw error;
    }
}

// Budget & Transactions API Functions
async function getTransactions(params = {}) {
    try {
        const queryParams = new URLSearchParams();
        if (params.type) queryParams.append('type', params.type);
        if (params.category) queryParams.append('category', params.category);
        if (params.start_date) queryParams.append('start_date', params.start_date);
        if (params.end_date) queryParams.append('end_date', params.end_date);
        if (params.month) queryParams.append('month', params.month);
        if (params.year) queryParams.append('year', params.year);
        
        const queryString = queryParams.toString();
        const endpoint = `/transactions${queryString ? `?${queryString}` : ''}`;
        const response = await apiRequest(endpoint, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get transactions error:', error);
        throw error;
    }
}

async function getTransaction(id) {
    try {
        const response = await apiRequest(`/transactions/${id}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get transaction error:', error);
        throw error;
    }
}

async function createTransaction(data) {
    try {
        const response = await apiRequest('/transactions', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Create transaction error:', error);
        throw error;
    }
}

async function updateTransaction(id, data) {
    try {
        const response = await apiRequest(`/transactions/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Update transaction error:', error);
        throw error;
    }
}

async function deleteTransaction(id) {
    try {
        const response = await apiRequest(`/transactions/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete transaction error:', error);
        throw error;
    }
}

async function getTransactionSummary(params = {}) {
    try {
        const queryParams = new URLSearchParams();
        if (params.month) queryParams.append('month', params.month);
        if (params.year) queryParams.append('year', params.year);
        
        const queryString = queryParams.toString();
        const endpoint = `/transactions/summary${queryString ? `?${queryString}` : ''}`;
        const response = await apiRequest(endpoint, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get transaction summary error:', error);
        throw error;
    }
}

async function getTransactionReports(params = {}) {
    try {
        const queryParams = new URLSearchParams();
        if (params.month) queryParams.append('month', params.month);
        if (params.year) queryParams.append('year', params.year);
        
        const queryString = queryParams.toString();
        const endpoint = `/transactions/reports${queryString ? `?${queryString}` : ''}`;
        const response = await apiRequest(endpoint, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get transaction reports error:', error);
        throw error;
    }
}

async function getBudgets(params = {}) {
    try {
        const queryParams = new URLSearchParams();
        if (params.category) queryParams.append('category', params.category);
        if (params.month) queryParams.append('month', params.month);
        if (params.year) queryParams.append('year', params.year);
        
        const queryString = queryParams.toString();
        const endpoint = `/budgets${queryString ? `?${queryString}` : ''}`;
        const response = await apiRequest(endpoint, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get budgets error:', error);
        throw error;
    }
}

async function createBudget(data) {
    try {
        const response = await apiRequest('/budgets', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Create budget error:', error);
        throw error;
    }
}

async function updateBudget(id, data) {
    try {
        const response = await apiRequest(`/budgets/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Update budget error:', error);
        throw error;
    }
}

async function deleteBudget(id) {
    try {
        const response = await apiRequest(`/budgets/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete budget error:', error);
        throw error;
    }
}

// Goals API Functions
async function getGoals() {
    try {
        const response = await apiRequest('/goals', {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get goals error:', error);
        throw error;
    }
}

async function getGoal(id) {
    try {
        const response = await apiRequest(`/goals/${id}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get goal error:', error);
        throw error;
    }
}

async function createGoal(data) {
    try {
        const response = await apiRequest('/goals', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Create goal error:', error);
        throw error;
    }
}

async function updateGoal(id, data) {
    try {
        const response = await apiRequest(`/goals/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Update goal error:', error);
        throw error;
    }
}

async function deleteGoal(id) {
    try {
        const response = await apiRequest(`/goals/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete goal error:', error);
        throw error;
    }
}

async function completeGoal(id) {
    try {
        const response = await apiRequest(`/goals/${id}/complete`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Complete goal error:', error);
        throw error;
    }
}

// Tasks API Functions
async function getTasks(goalId = null) {
    try {
        if (goalId) {
            // Get tasks for a specific goal
            const response = await apiRequest(`/goals/${goalId}/tasks`, {
                method: 'GET',
            });
            return response;
        } else {
            // Get all tasks (standalone and goal-linked) using the new endpoint
            const response = await apiRequest('/tasks', {
                method: 'GET',
            });
            return response;
        }
    } catch (error) {
        console.error('Get tasks error:', error);
        throw error;
    }
}

async function getTask(id) {
    try {
        const response = await apiRequest(`/tasks/${id}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get task error:', error);
        throw error;
    }
}

async function createTask(goalId, data) {
    try {
        // If goalId is provided, use the goal-specific endpoint
        // Otherwise, use the standalone tasks endpoint
        if (goalId) {
            // Create task linked to a goal
            data.goal_id = parseInt(goalId);
            const response = await apiRequest(`/goals/${goalId}/tasks`, {
                method: 'POST',
                body: data,
            });
            return response;
        } else {
            // Create standalone task (goal_id will be null)
            data.goal_id = null;
            const response = await apiRequest('/tasks', {
                method: 'POST',
                body: data,
            });
            return response;
        }
    } catch (error) {
        console.error('Create task error:', error);
        throw error;
    }
}

async function updateTask(id, data) {
    try {
        const response = await apiRequest(`/tasks/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Update task error:', error);
        throw error;
    }
}

async function deleteTask(id) {
    try {
        const response = await apiRequest(`/tasks/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete task error:', error);
        throw error;
    }
}

async function completeTask(id) {
    try {
        const response = await apiRequest(`/tasks/${id}/complete`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Complete task error:', error);
        throw error;
    }
}

// Habits API Functions
async function getHabits() {
    try {
        const response = await apiRequest('/habits', {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get habits error:', error);
        throw error;
    }
}

async function createHabit(data) {
    try {
        const response = await apiRequest('/habits', {
            method: 'POST',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Create habit error:', error);
        throw error;
    }
}

async function updateHabit(id, data) {
    try {
        const response = await apiRequest(`/habits/${id}`, {
            method: 'PUT',
            body: data,
        });
        return response;
    } catch (error) {
        console.error('Update habit error:', error);
        throw error;
    }
}

async function deleteHabit(id) {
    try {
        const response = await apiRequest(`/habits/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete habit error:', error);
        throw error;
    }
}

async function markHabitToday(id) {
    try {
        const response = await apiRequest(`/habits/${id}/mark-today`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Mark habit today error:', error);
        throw error;
    }
}

async function getHabitHistory(id) {
    try {
        const response = await apiRequest(`/habits/${id}/history`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get habit history error:', error);
        throw error;
    }
}

// Admin API Functions
async function getAdminAnalytics() {
    try {
        const response = await apiRequest('/admin/analytics', {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get admin analytics error:', error);
        throw error;
    }
}

async function getAdminUsers(params = {}) {
    try {
        const query = new URLSearchParams();
        if (params.role) query.append('role', params.role);
        if (params.status) query.append('status', params.status);
        const qs = query.toString() ? `?${query.toString()}` : '';
        const response = await apiRequest(`/admin/users${qs}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get admin users error:', error);
        throw error;
    }
}

async function getAdminAnnouncements(params = {}) {
    try {
        const query = new URLSearchParams();
        if (params.status) query.append('status', params.status);
        if (params.audience) query.append('audience', params.audience);
        const qs = query.toString() ? `?${query.toString()}` : '';
        const response = await apiRequest(`/admin/announcements${qs}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get admin announcements error:', error);
        throw error;
    }
}

async function getAdminLogs(params = {}) {
    try {
        const query = new URLSearchParams();
        // Backend currently supports type/level/date filters; keep params minimal for compatibility
        if (params.type) query.append('type', params.type);
        if (params.level) query.append('level', params.level);
        if (params.page) query.append('page', params.page);
        if (params.per_page) query.append('per_page', params.per_page);
        const qs = query.toString() ? `?${query.toString()}` : '';
        const response = await apiRequest(`/admin/logs${qs}`, {
            method: 'GET',
        });
        return response;
    } catch (error) {
        console.error('Get admin logs error:', error);
        throw error;
    }
}

async function updateAdminUserRole(id, role) {
    try {
        console.log('Updating admin role:', { id, role });
        const requestBody = { role: role };
        console.log('Request body:', requestBody);
        
        const response = await apiRequest(`/admin/users/${id}/role`, {
            method: 'PUT',
            body: requestBody,
        });
        console.log('Role update response:', response);
        return response;
    } catch (error) {
        console.error('Update admin user role error:', error);
        console.error('Full error object:', JSON.stringify(error, null, 2));
        // Include validation errors in the error message if available
        if (error.data && error.data.errors) {
            const validationErrors = Object.entries(error.data.errors)
                .map(([field, messages]) => `${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}`)
                .join('; ');
            error.message = `Validation failed: ${validationErrors}`;
        } else if (error.data && error.data.message) {
            error.message = error.data.message;
        }
        throw error;
    }
}

async function suspendAdminUser(id) {
    try {
        const response = await apiRequest(`/admin/users/${id}/suspend`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Suspend admin user error:', error);
        throw error;
    }
}

async function activateAdminUser(id) {
    try {
        const response = await apiRequest(`/admin/users/${id}/activate`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Activate admin user error:', error);
        throw error;
    }
}

async function updateAdminUser(id, data) {
    try {
        // Backend has specific endpoints, so we'll make multiple calls if needed
        const results = [];
        
        // Update role if changed
        if (data.role !== undefined) {
            try {
                const roleResult = await updateAdminUserRole(id, data.role);
                results.push(roleResult);
            } catch (error) {
                // If role update fails, still try other updates
                console.warn('Role update failed, continuing with other updates:', error);
                throw error; // Re-throw to show error to user
            }
        }
        
        // Update status if changed
        if (data.status === 'suspended') {
            try {
                const suspendResult = await suspendAdminUser(id);
                results.push(suspendResult);
            } catch (error) {
                console.warn('Status update (suspend) failed:', error);
                throw error;
            }
        } else if (data.status === 'active') {
            try {
                const activateResult = await activateAdminUser(id);
                results.push(activateResult);
            } catch (error) {
                console.warn('Status update (activate) failed:', error);
                throw error;
            }
        }
        
        // Note: Name/email updates are not supported by backend (returns 405)
        // Backend only supports role and status updates via specific endpoints
        if (data.name !== undefined || data.email !== undefined) {
            console.warn('Name/email updates are not supported by the backend API');
            // Don't throw error - just log a warning and continue with role/status updates
        }
        
        if (results.length === 0) {
            throw new Error('No valid fields to update');
        }
        
        return results[0]; // Return first result
    } catch (error) {
        console.error('Update admin user error:', error);
        // Include validation errors in the error message if available
        if (error.data && error.data.errors) {
            const validationErrors = Object.values(error.data.errors).flat().join(', ');
            error.message = `Validation failed: ${validationErrors}`;
        }
        throw error;
    }
}

async function deleteAdminUser(id) {
    try {
        const response = await apiRequest(`/admin/users/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete admin user error:', error);
        throw error;
    }
}

async function createAdminUser(data) {
    try {
        // Use signup endpoint with admin role
        const response = await apiRequest('/signup', {
            method: 'POST',
            body: {
                name: data.name,
                email: data.email,
                password: data.password,
                password_confirmation: data.password_confirmation || data.password,
                role: data.role || 'admin',
            },
        });
        return response;
    } catch (error) {
        console.error('Create admin user error:', error);
        throw error;
    }
}

async function createStudent(data) {
    try {
        // Use signup endpoint with student role
        const response = await apiRequest('/signup', {
            method: 'POST',
            body: {
                name: data.name,
                email: data.email,
                password: data.password,
                password_confirmation: data.password_confirmation || data.password,
                role: 'student',
            },
        });
        return response;
    } catch (error) {
        console.error('Create student error:', error);
        throw error;
    }
}

async function createAnnouncement(data) {
    try {
        // Build request body, only including fields that have values
        const body = {
            title: data.title,
            message: data.message,
            audience: data.audience || 'all',
        };

        // Only include target_user_id if audience is 'single' and it's provided
        if (data.audience === 'single' && data.target_user_id) {
            body.target_user_id = data.target_user_id;
        }

        // Only include scheduled_at if it's provided
        if (data.scheduled_at) {
            body.scheduled_at = data.scheduled_at;
        }

        const response = await apiRequest('/admin/announcements', {
            method: 'POST',
            body: body,
        });
        return response;
    } catch (error) {
        console.error('Create announcement error:', error);
        throw error;
    }
}

async function updateAnnouncement(id, data) {
    try {
        // Build request body, only including fields that have values
        const body = {
            title: data.title,
            message: data.message,
            audience: data.audience || 'all',
        };

        // Only include target_user_id if audience is 'single' and it's provided
        if (data.audience === 'single' && data.target_user_id) {
            body.target_user_id = data.target_user_id;
        }

        // Only include scheduled_at if it's provided
        if (data.scheduled_at) {
            body.scheduled_at = data.scheduled_at;
        }

        const response = await apiRequest(`/admin/announcements/${id}`, {
            method: 'PUT',
            body: body,
        });
        return response;
    } catch (error) {
        console.error('Update announcement error:', error);
        throw error;
    }
}

async function deleteAnnouncement(id) {
    try {
        const response = await apiRequest(`/admin/announcements/${id}`, {
            method: 'DELETE',
        });
        return response;
    } catch (error) {
        console.error('Delete announcement error:', error);
        throw error;
    }
}

async function sendAnnouncement(id) {
    try {
        const response = await apiRequest(`/admin/announcements/${id}/send`, {
            method: 'POST',
        });
        return response;
    } catch (error) {
        console.error('Send announcement error:', error);
        throw error;
    }
}

// Export functions
window.api = {
    register,
    login,
    logout,
    getCurrentUser,
    forgotPassword,
    resetPassword,
    getToken,
    setToken,
    removeToken,
    API_BASE_URL,
    // Semesters
    getSemesters,
    getSemester,
    createSemester,
    updateSemester,
    deleteSemester,
    // Courses
    getCourses,
    createCourse,
    updateCourse,
    deleteCourse,
    // Assessments
    getAssessments,
    getAssessment,
    createAssessment,
    updateAssessment,
    deleteAssessment,
    // Events
    getEvents,
    getEvent,
    createEvent,
    updateEvent,
    deleteEvent,
    // Notes
    getNotes,
    getNote,
    createNote,
    updateNote,
    deleteNote,
    pinNote,
    favoriteNote,
    searchNotes,
    // Attachments
    uploadAttachment,
    downloadAttachment,
    deleteAttachment,
    // Transactions
    getTransactions,
    getTransaction,
    createTransaction,
    updateTransaction,
    deleteTransaction,
    getTransactionSummary,
    getTransactionReports,
    // Budgets
    getBudgets,
    createBudget,
    updateBudget,
    deleteBudget,
    // Goals
    getGoals,
    getGoal,
    createGoal,
    updateGoal,
    deleteGoal,
    completeGoal,
    // Tasks
    getTasks,
    getTask,
    createTask,
    updateTask,
    deleteTask,
    completeTask,
    // Habits
    getHabits,
    createHabit,
    updateHabit,
    deleteHabit,
    markHabitToday,
    getHabitHistory,
    // Admin
    getAdminAnalytics,
    getAdminUsers,
    getAdminAnnouncements,
    getAdminLogs,
    updateAdminUser,
    updateAdminUserRole,
    suspendAdminUser,
    activateAdminUser,
    deleteAdminUser,
    createAdminUser,
    createStudent,
    createAnnouncement,
    updateAnnouncement,
    deleteAnnouncement,
    sendAnnouncement,
};

