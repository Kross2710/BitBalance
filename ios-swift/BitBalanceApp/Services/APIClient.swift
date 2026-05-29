import Foundation

enum APIError: Error, LocalizedError {
    case invalidResponse
    case serverMessage(String)

    var errorDescription: String? {
        switch self {
        case .invalidResponse:
            return "Invalid server response."
        case .serverMessage(let message):
            return message
        }
    }
}

final class APIClient {
    private let baseURL: URL
    private let session: URLSession

    init(baseURL: URL, session: URLSession = .shared) {
        self.baseURL = baseURL
        self.session = session
    }

    func login(email: String, password: String) async throws -> UserSession {
        let response: APIEnvelope<UserSession> = try await postForm(
            path: "api/auth/login.php",
            fields: [
                "email": email,
                "password": password
            ]
        )

        if response.ok, let user = response.data {
            return user
        }

        throw APIError.serverMessage(response.message ?? "Login failed.")
    }

    func loadCurrentUser() async throws -> UserSession {
        let response: APIEnvelope<UserSession> = try await get(path: "api/me.php")
        if response.ok, let user = response.data {
            return user
        }
        throw APIError.serverMessage(response.message ?? "Authentication required.")
    }

    func loadDashboardSummary() async throws -> DashboardSummary {
        let response: APIEnvelope<DashboardSummary> = try await get(path: "api/dashboard/summary.php")
        if response.ok, let summary = response.data {
            return summary
        }
        throw APIError.serverMessage(response.message ?? "Unable to load dashboard.")
    }

    func logout() async throws {
        let _: APIEnvelope<EmptyPayload> = try await postForm(path: "api/auth/logout.php", fields: [:])
    }

    private func get<T: Decodable>(path: String) async throws -> T {
        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = "GET"
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        return try await send(request)
    }

    private func postForm<T: Decodable>(path: String, fields: [String: String]) async throws -> T {
        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.httpBody = fields
            .map { key, value in
                "\(urlEncode(key))=\(urlEncode(value))"
            }
            .joined(separator: "&")
            .data(using: .utf8)

        return try await send(request)
    }

    private func send<T: Decodable>(_ request: URLRequest) async throws -> T {
        let (data, response) = try await session.data(for: request)

        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }

        guard (200...299).contains(httpResponse.statusCode) else {
            let decoder = JSONDecoder()
            decoder.keyDecodingStrategy = .convertFromSnakeCase
            if let errorPayload = try? decoder.decode(APIEnvelope<EmptyPayload>.self, from: data),
               let message = errorPayload.message {
                throw APIError.serverMessage(message)
            }
            throw APIError.invalidResponse
        }

        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(T.self, from: data)
    }

    private func urlEncode(_ value: String) -> String {
        var allowed = CharacterSet.urlQueryAllowed
        allowed.remove(charactersIn: ":#[]@!$&'()*+,;=")
        return value.addingPercentEncoding(withAllowedCharacters: allowed) ?? value
    }
}
