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

    func login(email: String, password: String, remember: Bool = true) async throws -> UserSession {
        var fields = [
            "email": email,
            "password": password
        ]
        if remember {
            fields["remember"] = "1"
        }

        let response: APIEnvelope<UserSession> = try await postForm(
            path: "api/auth/login.php",
            fields: fields
        )

        if response.ok, let user = response.data {
            return user
        }

        throw APIError.serverMessage(response.message ?? "Login failed.")
    }

    func register(firstName: String, lastName: String, email: String, password: String, confirmPassword: String) async throws -> UserSession {
        let response: APIEnvelope<UserSession> = try await postForm(
            path: "api/auth/register.php",
            fields: [
                "first_name": firstName,
                "last_name": lastName,
                "email": email,
                "password": password,
                "confirm_password": confirmPassword
            ]
        )

        if response.ok, let user = response.data {
            return user
        }

        throw APIError.serverMessage(response.message ?? "Registration failed.")
    }

    // MARK: - Onboarding

    func saveOnboarding(_ payload: OnboardingPayload) async throws -> OnboardingResult {
        var fields: [String: String] = [
            "gender":         payload.gender,
            "age":            String(payload.age),
            "height":         String(payload.height),
            "weight":         String(payload.weight),
            "activity_level": payload.activityLevel,
            "goal_mode":      payload.goalMode,
            "weekly_rate":    String(payload.weeklyRate)
        ]
        if let tw = payload.targetWeight {
            fields["target_weight"] = String(tw)
        }
        let response: APIEnvelope<OnboardingResult> = try await postForm(
            path: "api/onboarding/save.php",
            fields: fields
        )
        if response.ok, let data = response.data {
            return data
        }
        throw APIError.serverMessage(response.message ?? "Could not save your plan.")
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

    func loadDashboardDay(date: String? = nil) async throws -> DashboardDayPayload {
        var queryItems: [URLQueryItem] = []
        if let date = date, !date.isEmpty {
            queryItems.append(URLQueryItem(name: "date", value: date))
        }

        let response: APIEnvelope<DashboardDayPayload> = try await get(
            path: "api/dashboard/day.php",
            queryItems: queryItems
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to load dashboard day.")
    }

    func loadIntakeHistory(limit: Int = 50) async throws -> IntakeHistoryPayload {
        let response: APIEnvelope<IntakeHistoryPayload> = try await get(
            path: "api/intake/history.php",
            queryItems: [URLQueryItem(name: "limit", value: String(limit))]
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to load intake history.")
    }

    func createIntake(_ payload: IntakeFormPayload) async throws -> IntakeMutationPayload {
        let response: APIEnvelope<IntakeMutationPayload> = try await postForm(
            path: "api/intake/create.php",
            fields: intakeFields(payload)
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to create intake entry.")
    }

    func updateIntake(id: Int, payload: IntakeFormPayload) async throws -> IntakeMutationPayload {
        var fields = intakeFields(payload)
        fields["intake_id"] = String(id)

        let response: APIEnvelope<IntakeMutationPayload> = try await postForm(
            path: "api/intake/update.php",
            fields: fields
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to update intake entry.")
    }

    func deleteIntake(id: Int) async throws -> IntakeDeletePayload {
        let response: APIEnvelope<IntakeDeletePayload> = try await postForm(
            path: "api/intake/delete.php",
            fields: ["intake_id": String(id)]
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to delete intake entry.")
    }

    func loadProfile() async throws -> ProfilePayload {
        let response: APIEnvelope<ProfilePayload> = try await get(path: "api/profile/get.php")
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to load profile.")
    }

    func updateProfile(_ payload: ProfileUpdatePayload) async throws -> ProfilePayload {
        let response: APIEnvelope<ProfilePayload> = try await postForm(
            path: "api/profile/update.php",
            fields: [
                "first_name": payload.firstName,
                "last_name": payload.lastName,
                "user_name": payload.userName,
                "email": payload.email,
                "bio": payload.bio,
                "theme_preference": payload.themePreference,
                "calorie_goal": payload.calorieGoal,
                "age": payload.age,
                "gender": payload.gender,
                "weight": payload.weight,
                "height": payload.height
            ]
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to update profile.")
    }

    func logout() async throws {
        let _: APIEnvelope<EmptyPayload> = try await postForm(path: "api/auth/logout.php", fields: [:])
    }

    // MARK: - AI Coach

    func loadConversations() async throws -> [Conversation] {
        let response: APIEnvelope<[Conversation]> = try await get(path: "api/ai-coach/conversations.php")
        if response.ok, let data = response.data {
            return data
        }
        throw APIError.serverMessage(response.message ?? "Unable to load conversations.")
    }

    func loadMessages(conversationId: Int) async throws -> ConversationMessagesPayload {
        let response: APIEnvelope<ConversationMessagesPayload> = try await get(
            path: "api/ai-coach/messages.php",
            queryItems: [URLQueryItem(name: "conversation_id", value: String(conversationId))]
        )
        if response.ok, let data = response.data {
            return data
        }
        throw APIError.serverMessage(response.message ?? "Unable to load messages.")
    }

    func sendMessage(_ message: String, conversationId: Int = 0, imageData: Data? = nil) async throws -> SendMessagePayload {
        let now = ISO8601DateFormatter().string(from: Date())
        let tzOffset = String(TimeZone.current.secondsFromGMT() / -60)  // JS convention: positive = west
        
        // If there's no image, use the standard postForm method
        if imageData == nil {
            var fields: [String: String] = ["message": message]
            if conversationId > 0 {
                fields["conversation_id"] = String(conversationId)
            }
            fields["client_now"] = now
            fields["client_tz_offset"] = tzOffset
            
            let response: APIEnvelope<SendMessagePayload> = try await postForm(
                path: "api/ai-coach/send.php",
                fields: fields
            )
            if response.ok, let data = response.data {
                return data
            }
            throw APIError.serverMessage(response.message ?? "Unable to send message.")
        }
        
        // If there is an image, perform a boundary-separated multipart request
        let boundary = "Boundary-\(UUID().uuidString)"
        var request = URLRequest(url: endpointURL(path: "api/ai-coach/send.php"))
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        
        var body = Data()
        
        // Helper to append fields
        func addTextField(name: String, value: String) {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(name)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        
        addTextField(name: "message", value: message)
        if conversationId > 0 {
            addTextField(name: "conversation_id", value: String(conversationId))
        }
        addTextField(name: "client_now", value: now)
        addTextField(name: "client_tz_offset", value: tzOffset)
        
        if let imgData = imageData {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"image\"; filename=\"image.jpg\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
            body.append(imgData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        request.httpBody = body
        
        let responsePayload: APIEnvelope<SendMessagePayload> = try await send(request)
        if responsePayload.ok, let data = responsePayload.data {
            return data
        }
        throw APIError.serverMessage(responsePayload.message ?? "Unable to send message.")
    }

    func lookupBarcode(barcode: String) async throws -> BarcodeProductPayload {
        let response: APIEnvelope<BarcodeProductPayload> = try await postForm(
            path: "api/intake/lookup_barcode.php",
            fields: ["barcode": barcode]
        )
        if response.ok, let data = response.data {
            return data
        }
        throw APIError.serverMessage(response.message ?? "Barcode not found.")
    }

    func deleteConversation(id: Int) async throws -> DeleteConversationPayload {
        let response: APIEnvelope<DeleteConversationPayload> = try await postForm(
            path: "api/ai-coach/delete.php",
            fields: ["conversation_id": String(id)]
        )
        if response.ok, let data = response.data {
            return data
        }
        throw APIError.serverMessage(response.message ?? "Unable to delete conversation.")
    }

    // MARK: - Friends & Social Hub Actions

    func loadSocialHub() async throws -> SocialPollPayload {
        let response: APIEnvelope<SocialPollPayload> = try await get(
            path: "api/social/action.php",
            queryItems: [URLQueryItem(name: "action", value: "poll")]
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to load friends data.")
    }

    func loadSocialLeaderboard(period: String = "weekly") async throws -> LeaderboardPayload {
        let response: APIEnvelope<LeaderboardPayload> = try await get(
            path: "api/social/action.php",
            queryItems: [
                URLQueryItem(name: "action", value: "leaderboard"),
                URLQueryItem(name: "period", value: period)
            ]
        )
        if response.ok, let payload = response.data {
            return payload
        }
        throw APIError.serverMessage(response.message ?? "Unable to load leaderboard.")
    }

    func searchUsers(query: String) async throws -> [SearchUserResult] {
        let response: APIEnvelope<SearchUsersPayload> = try await postForm(
            path: "api/social/action.php",
            fields: [
                "action": "search",
                "q": query
            ]
        )
        if response.ok, let payload = response.data {
            return payload.results
        }
        throw APIError.serverMessage(response.message ?? "User search failed.")
    }

    func sendFriendRequest(targetId: Int) async throws {
        let response: APIEnvelope<EmptyPayload> = try await postForm(
            path: "api/social/action.php",
            fields: [
                "action": "send",
                "target_id": String(targetId)
            ]
        )
        if !response.ok {
            throw APIError.serverMessage(response.message ?? "Failed to send friend request.")
        }
    }

    func respondFriendRequest(requestId: Int, accept: Bool) async throws {
        let response: APIEnvelope<EmptyPayload> = try await postForm(
            path: "api/social/action.php",
            fields: [
                "action": accept ? "accept" : "reject",
                "request_id": String(requestId)
            ]
        )
        if !response.ok {
            throw APIError.serverMessage(response.message ?? "Failed to respond to request.")
        }
    }

    func cancelFriendRequest(requestId: Int) async throws {
        let response: APIEnvelope<EmptyPayload> = try await postForm(
            path: "api/social/action.php",
            fields: [
                "action": "cancel",
                "request_id": String(requestId)
            ]
        )
        if !response.ok {
            throw APIError.serverMessage(response.message ?? "Failed to cancel request.")
        }
    }

    func unfriend(targetId: Int) async throws {
        let response: APIEnvelope<EmptyPayload> = try await postForm(
            path: "api/social/action.php",
            fields: [
                "action": "unfriend",
                "target_id": String(targetId)
            ]
        )
        if !response.ok {
            throw APIError.serverMessage(response.message ?? "Failed to unfriend.")
        }
    }

    private func get<T: Decodable>(path: String, queryItems: [URLQueryItem] = []) async throws -> T {
        var request = URLRequest(url: endpointURL(path: path, queryItems: queryItems))
        request.httpMethod = "GET"
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        return try await send(request)
    }

    private func postForm<T: Decodable>(path: String, fields: [String: String]) async throws -> T {
        var request = URLRequest(url: endpointURL(path: path))
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

    private func endpointURL(path: String, queryItems: [URLQueryItem] = []) -> URL {
        let base = baseURL.absoluteString.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        let relativePath = path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        var components = URLComponents(string: "\(base)/\(relativePath)")!
        if !queryItems.isEmpty {
            components.queryItems = queryItems
        }
        return components.url!
    }

    private func urlEncode(_ value: String) -> String {
        var allowed = CharacterSet.urlQueryAllowed
        allowed.remove(charactersIn: ":#[]@!$&'()*+,;=")
        return value.addingPercentEncoding(withAllowedCharacters: allowed) ?? value
    }

    private func intakeFields(_ payload: IntakeFormPayload) -> [String: String] {
        [
            "food_item": payload.foodItem,
            "calories": String(payload.calories),
            "protein": String(payload.protein),
            "carbs": String(payload.carbs),
            "fat": String(payload.fat),
            "meal_category": payload.mealCategory
        ]
    }
}
