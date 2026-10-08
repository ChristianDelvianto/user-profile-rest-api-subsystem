import axios from 'axios'

const Api = axios.create({
    // 'baseURL' defined from src/app.js
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    },
    withCredentials: true,
    withXSRFToken: true
})

/**
 * Since v1 is using cookie-based authentication,
 * We do not need to configure axios interceptors.
 */

export default Api
