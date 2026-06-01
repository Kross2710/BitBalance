import SwiftUI
import AVFoundation
import UIKit

// MARK: - Asymmetric Rounded Corner Shape
struct AsymmetricCornerShape: Shape {
    let topLeft: CGFloat
    let topRight: CGFloat
    let bottomLeft: CGFloat
    let bottomRight: CGFloat
    
    func path(in rect: CGRect) -> Path {
        var path = Path()
        let w = rect.size.width
        let h = rect.size.height
        
        // Make sure corners don't exceed dimensions
        let tr = min(min(topRight, h/2), w/2)
        let tl = min(min(topLeft, h/2), w/2)
        let bl = min(min(bottomLeft, h/2), w/2)
        let br = min(min(bottomRight, h/2), w/2)
        
        path.move(to: CGPoint(x: w / 2, y: 0))
        path.addLine(to: CGPoint(x: w - tr, y: 0))
        path.addArc(center: CGPoint(x: w - tr, y: tr), radius: tr, startAngle: Angle(degrees: -90), endAngle: Angle(degrees: 0), clockwise: false)
        path.addLine(to: CGPoint(x: w, y: h - br))
        path.addArc(center: CGPoint(x: w - br, y: h - br), radius: br, startAngle: Angle(degrees: 0), endAngle: Angle(degrees: 90), clockwise: false)
        path.addLine(to: CGPoint(x: bl, y: h))
        path.addArc(center: CGPoint(x: bl, y: h - bl), radius: bl, startAngle: Angle(degrees: 90), endAngle: Angle(degrees: 180), clockwise: false)
        path.addLine(to: CGPoint(x: 0, y: tl))
        path.addArc(center: CGPoint(x: tl, y: tl), radius: tl, startAngle: Angle(degrees: 180), endAngle: Angle(degrees: 270), clockwise: false)
        path.closeSubpath()
        
        return path
    }
}

// MARK: - Tab Root

struct AIChatView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var conversations: [Conversation] = []
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var showNewChat = false

    var body: some View {
        NavigationStack {
            ZStack {
                // Subtle brand background gradient
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                Group {
                    if isLoading && conversations.isEmpty {
                        ProgressView("Loading conversations...")
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                    } else if conversations.isEmpty {
                        emptyState
                    } else {
                        conversationList
                    }
                }
            }
            .navigationTitle("AI Coach")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        showNewChat = true
                    } label: {
                        Image(systemName: "square.and.pencil")
                            .font(BBFont.bodyBold)
                            .foregroundColor(BBColors.primary)
                    }
                }
            }
            .navigationDestination(isPresented: $showNewChat) {
                ChatDetailView(conversationId: nil, onConversationCreated: { _ in
                    Task { await loadConversations() }
                })
            }
            .task {
                await loadConversations()
            }
            .refreshable {
                await loadConversations()
            }
        }
    }

    private var emptyState: some View {
        VStack(spacing: 24) {
            Spacer()
            
            VStack(spacing: 16) {
                ZStack {
                    Circle()
                        .fill(BBColors.surface)
                        .frame(width: 100, height: 100)
                        .overlay(
                            Circle()
                                .stroke(BBColors.primary, lineWidth: 3)
                        )
                        .background(
                            Circle()
                                .fill(BBColors.primaryHover)
                                .offset(y: 6)
                        )
                        .shadow(color: Color.black.opacity(0.1), radius: 6, x: 0, y: 3)
                    
                    Text("🤖")
                        .font(.system(size: 54))
                }
                .padding(.bottom, 8)
                
                Text("Your AI Coach")
                    .font(BBFont.titleBold)
                    .foregroundColor(BBColors.text)
                
                Text("Get personalized nutrition advice,\nmeal suggestions, and fitness tips.")
                    .font(BBFont.font(15, .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .multilineTextAlignment(.center)
            }
            .bbCard()
            
            Button {
                showNewChat = true
            } label: {
                Label("Start a Chat", systemImage: "plus.bubble")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(BBButtonStyle(
                backgroundColor: BBColors.primary,
                shadowColor: BBColors.primaryHover
            ))
            
            Spacer()
            Spacer()
        }
        .padding(24)
    }

    private var conversationList: some View {
        List {
            Text("YOUR CONVERSATIONS")
                .font(.system(size: 11, weight: .heavy))
                .foregroundColor(BBColors.textSecondary)
                .listRowSeparator(.hidden)
                .listRowBackground(Color.clear)
                .padding(.horizontal, 8)
                .padding(.top, 12)
                .padding(.bottom, 2)
            
            ForEach(conversations) { conv in
                NavigationLink {
                    ChatDetailView(conversationId: conv.id, onConversationCreated: nil)
                } label: {
                    HStack(spacing: 12) {
                        Image(systemName: "bubble.left.and.bubble.right.fill")
                            .foregroundColor(BBColors.primary)
                            .font(BBFont.font(BBFont.lg, .regular))
                            .frame(width: 32)
                        
                        VStack(alignment: .leading, spacing: 4) {
                            Text(conv.title)
                                .font(BBFont.font(15, .black))
                                .foregroundColor(BBColors.text)
                                .lineLimit(1)
                            
                            Text("Last updated: \(conv.updatedAt)")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundColor(BBColors.textSecondary)
                        }
                        
                        Spacer()
                        
                        Image(systemName: "chevron.right")
                            .font(BBFont.font(12, .heavy))
                            .foregroundColor(BBColors.textMuted)
                    }
                    .padding(.vertical, 12)
                    .padding(.horizontal, 8)
                }
                .listRowSeparatorTint(BBColors.border)
                .listRowBackground(Color.clear)
            }
            .onDelete(perform: deleteConversation)
        }
        .listStyle(.plain)
    }

    private func loadConversations() async {
        isLoading = true
        do {
            conversations = try await session.loadConversations()
        } catch {
            errorMessage = error.localizedDescription
        }
        isLoading = false
    }

    private func deleteConversation(at offsets: IndexSet) {
        for index in offsets {
            let conv = conversations[index]
            conversations.remove(at: index)
            Task {
                do {
                    _ = try await session.deleteConversation(id: conv.id)
                } catch {
                    errorMessage = error.localizedDescription
                    await loadConversations()
                }
            }
        }
    }
}

// MARK: - Chat Detail

struct ChatDetailView: View {
    @EnvironmentObject private var session: SessionStore
    let conversationId: Int?
    let onConversationCreated: ((Int) -> Void)?

    @State private var messages: [ChatMessage] = []
    @State private var inputText = ""
    @State private var isLoading = false
    @State private var isSending = false
    @State private var errorMessage: String?
    @State private var activeConversationId: Int?
    @State private var conversationTitle = "New Chat"
    
    // Photo / Camera capture states
    @State private var selectedImage: UIImage? = nil
    @State private var showImagePicker = false
    @State private var imagePickerSourceType: UIImagePickerController.SourceType = .photoLibrary
    @State private var showPhotoOptions = false
    
    @FocusState private var isInputFocused: Bool

    var body: some View {
        ZStack {
            // Subtle brand background gradient
            BBColors.backgroundGradient
                .ignoresSafeArea()
            
            VStack(spacing: 0) {
                // Messages
                ScrollViewReader { proxy in
                    ScrollView {
                        LazyVStack(spacing: 16) {
                            if messages.isEmpty && !isLoading {
                                chatWelcome
                            }
                            ForEach(messages) { msg in
                                MessageBubble(message: msg)
                                    .id(msg.id)
                            }
                            if isSending {
                                TypingIndicator()
                                    .id("typing")
                            }
                        }
                        .padding(.vertical, 16)
                    }
                    .onChange(of: messages.count) { _ in
                        withAnimation {
                            if let lastId = messages.last?.id {
                                proxy.scrollTo(lastId, anchor: .bottom)
                            }
                        }
                    }
                    .onChange(of: isSending) { sending in
                        if sending {
                            withAnimation {
                                proxy.scrollTo("typing", anchor: .bottom)
                            }
                        }
                    }
                }

                // Error
                if let error = errorMessage {
                    HStack(spacing: 8) {
                        Image(systemName: "exclamationmark.triangle.fill")
                        Text(error)
                    }
                    .font(BBFont.font(BBFont.sm, .bold))
                    .bbAlert(isSuccess: false)
                    .padding(.horizontal)
                    .padding(.bottom, 8)
                }

                // Selected image thumbnail preview above composer bar
                if let selectedImage = selectedImage {
                    HStack {
                        ZStack(alignment: .topTrailing) {
                            Image(uiImage: selectedImage)
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                                .frame(width: 72, height: 72)
                                .cornerRadius(12)
                                .overlay(
                                    RoundedRectangle(cornerRadius: 12)
                                        .stroke(BBColors.border, lineWidth: 1.5)
                                )
                            
                            Button {
                                withAnimation {
                                    self.selectedImage = nil
                                }
                            } label: {
                                Image(systemName: "xmark.circle.fill")
                                    .font(BBFont.font(BBFont.xl, .regular))
                                    .foregroundColor(.red)
                                    .background(Circle().fill(Color.white))
                            }
                            .offset(x: 6, y: -6)
                        }
                        .padding(.leading, 24)
                        Spacer()
                    }
                    .padding(.bottom, 8)
                    .transition(.scale.combined(with: .opacity))
                }

                // Pill-shaped Composer Input Bar
                HStack(spacing: 10) {
                    // Attachment Trigger Button
                    Button {
                        showPhotoOptions = true
                    } label: {
                        Image(systemName: "camera.fill")
                            .font(BBFont.font(BBFont.xl, .regular))
                            .foregroundColor(BBColors.primary)
                    }
                    .disabled(isSending)
                    
                    TextField("Ask your AI Coach...", text: $inputText, axis: .vertical)
                        .lineLimit(1...5)
                        .disabled(isSending)
                        .focused($isInputFocused)
                        .font(BBFont.font(BBFont.sm, .bold))
                        .padding(.vertical, 8)
                    
                    Button {
                        sendMessage()
                    } label: {
                        Image(systemName: isSending ? "hourglass" : "arrow.up.circle.fill")
                            .font(BBFont.font(28, .bold))
                            .foregroundColor((inputText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && selectedImage == nil) || isSending ? BBColors.textMuted : BBColors.primary)
                    }
                    .disabled((inputText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && selectedImage == nil) || isSending)
                }
                .padding(.horizontal, 14)
                .padding(.vertical, 6)
                .background(BBColors.surfaceAlt)
                .cornerRadius(BBRadius.lg)
                .overlay(
                    RoundedRectangle(cornerRadius: BBRadius.lg)
                        .stroke(isInputFocused ? BBColors.primary : BBColors.border, lineWidth: 2)
                        .allowsHitTesting(false)
                )
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(BBColors.surface)
            }
        }
        .navigationTitle(conversationTitle)
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog("Upload Photo for AI", isPresented: $showPhotoOptions, titleVisibility: .visible) {
            Button("Camera (Take Photo)") {
                imagePickerSourceType = .camera
                showImagePicker = true
            }
            Button("Photo Library (Choose)") {
                imagePickerSourceType = .photoLibrary
                showImagePicker = true
            }
            Button("Cancel", role: .cancel) {}
        }
        .sheet(isPresented: $showImagePicker) {
            ImagePicker(sourceType: imagePickerSourceType, onImagePicked: { img in
                selectedImage = img
                showImagePicker = false
            }, onCancel: {
                showImagePicker = false
            })
        }
        .task {
            if let id = conversationId {
                activeConversationId = id
                await loadMessages(id: id)
            }
        }
    }

    private var chatWelcome: some View {
        VStack(spacing: 24) {
            // Gradient Icon Circle
            ZStack {
                Circle()
                    .fill(BBColors.primaryGradient)
                    .frame(width: 80, height: 80)
                    .overlay(
                        Circle()
                            .stroke(BBColors.primaryHover, lineWidth: 2)
                    )
                    .background(
                        Circle()
                            .fill(BBColors.primaryHover)
                            .offset(y: 4)
                    )
                    .shadow(color: Color.black.opacity(0.08), radius: 4, x: 0, y: 2)
                
                Text("✨")
                    .font(.system(size: 38))
            }
            .padding(.top, 24)
            .padding(.bottom, 4)
            
            VStack(spacing: 8) {
                Text("How can I help you today?")
                    .font(BBFont.font(BBFont.xl, .black))
                    .foregroundColor(BBColors.text)
                
                Text("Ask about nutrition, meal ideas, or your daily goals.")
                    .font(BBFont.font(13, .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .multilineTextAlignment(.center)
            }
            .padding(.horizontal, 24)
            
            // Clickable Suggestion Presets
            VStack(spacing: 12) {
                SuggestionRow(text: "What should I eat for dinner? 🍽️") {
                    inputText = "What should I eat for dinner? 🍽️"
                    sendMessage()
                }
                SuggestionRow(text: "How do I hit my protein goal today? 🍗") {
                    inputText = "How do I hit my protein goal today? 🍗"
                    sendMessage()
                }
                SuggestionRow(text: "Give me a quick 1500 kcal plan 📋") {
                    inputText = "Give me a quick 1500 kcal plan 📋"
                    sendMessage()
                }
            }
            .padding(.horizontal, 16)
        }
    }

    private func loadMessages(id: Int) async {
        isLoading = true
        do {
            let payload = try await session.loadMessages(conversationId: id)
            messages = payload.messages
            conversationTitle = payload.conversation.title
        } catch {
            errorMessage = error.localizedDescription
        }
        isLoading = false
    }

    private func sendMessage() {
        let text = inputText.trimmingCharacters(in: .whitespacesAndNewlines)
        if text.isEmpty && selectedImage == nil { return }
        
        inputText = ""
        errorMessage = nil
        
        let img = selectedImage
        withAnimation {
            selectedImage = nil // Reset preview haptic
        }
        
        let imageData = img?.jpegData(compressionQuality: 0.8)

        // Optimistically add user message with temp ID
        let tempUserMsg = ChatMessage(
            id: -(messages.count + 1),
            role: "user",
            content: text,
            imagePath: nil,
            createdAt: ISO8601DateFormatter().string(from: Date())
        )
        messages.append(tempUserMsg)

        Task {
            isSending = true
            do {
                let payload = try await session.sendMessage(
                    text,
                    conversationId: activeConversationId ?? 0,
                    imageData: imageData
                )

                // Replace temp user message with real one
                if let idx = messages.firstIndex(where: { $0.id == tempUserMsg.id }) {
                    messages[idx] = ChatMessage(
                        id: tempUserMsg.id,
                        role: payload.userMessage.role,
                        content: payload.userMessage.content,
                        imagePath: payload.userMessage.imagePath,
                        createdAt: payload.userMessage.createdAt,
                        foodLogSuggestions: payload.userMessage.foodLogSuggestions
                    )
                }
                var assistantMessage = payload.assistantMessage
                if assistantMessage.foodLogSuggestions == nil || assistantMessage.foodLogSuggestions?.isEmpty == true {
                    assistantMessage.foodLogSuggestions = payload.foodLogSuggestions
                }
                messages.append(assistantMessage)

                // Update conversation ID for new chats
                if activeConversationId == nil {
                    activeConversationId = payload.conversationId
                    conversationTitle = String(text.prefix(60))
                    onConversationCreated?(payload.conversationId)
                }
            } catch {
                errorMessage = error.localizedDescription
                // Remove optimistic message on failure
                messages.removeAll { $0.id == tempUserMsg.id }
            }
            isSending = false
        }
    }
}

// MARK: - Reusable Suggestion Preset Card Row
private struct SuggestionRow: View {
    let text: String
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack {
                Text(text)
                    .font(BBFont.font(BBFont.sm, .bold))
                    .foregroundColor(BBColors.text)
                Spacer()
                Image(systemName: "arrow.right.circle.fill")
                    .foregroundColor(BBColors.primary)
                    .font(BBFont.font(BBFont.lg, .regular))
            }
            .bbCard(radius: BBRadius.md, padding: 14)
        }
    }
}

// MARK: - Message Bubble

private struct MessageBubble: View {
    @EnvironmentObject private var session: SessionStore
    let message: ChatMessage

    @State private var loggedSuggestionIds: Set<String> = []
    @State private var loggingSuggestionIds: Set<String> = []
    @State private var logError: String?

    private var isUser: Bool { message.role == "user" }

    var body: some View {
        let userShape = AsymmetricCornerShape(topLeft: 18, topRight: 18, bottomLeft: 18, bottomRight: 4)
        let coachShape = AsymmetricCornerShape(topLeft: 18, topRight: 18, bottomLeft: 4, bottomRight: 18)

        HStack(alignment: .bottom, spacing: 10) {
            if !isUser {
                // Assistant Sparkle Gradient Avatar with Shadow
                ZStack {
                    Circle()
                        .fill(BBColors.primaryGradient)
                        .frame(width: 36, height: 36)
                        .overlay(
                            Circle()
                                .stroke(BBColors.primaryHover, lineWidth: 1.5)
                        )
                        .background(
                            Circle()
                                .fill(BBColors.primaryHover)
                                .offset(y: 2)
                        )
                    Text("✨")
                        .font(BBFont.font(15, .regular))
                }
                .padding(.bottom, 16)
            } else {
                Spacer(minLength: 60)
            }

            VStack(alignment: isUser ? .trailing : .leading, spacing: 6) {
                VStack(alignment: .leading, spacing: 6) {
                    // Optional sent photo image payload
                    if let path = message.imagePath, !path.isEmpty, let url = URL(string: AppConfig.baseURL.absoluteString + "/" + path) {
                        AsyncImage(url: url) { phase in
                            switch phase {
                            case .success(let image):
                                image
                                    .resizable()
                                    .aspectRatio(contentMode: .fill)
                                    .frame(maxWidth: 200, maxHeight: 160)
                                    .cornerRadius(12)
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 12)
                                            .stroke(isUser ? BBColors.primary.opacity(0.3) : BBColors.border, lineWidth: 1.5)
                                    )
                                    .padding(.bottom, 4)
                            case .failure:
                                EmptyView()
                            case .empty:
                                ProgressView()
                                    .frame(width: 200, height: 160)
                            @unknown default:
                                EmptyView()
                            }
                        }
                    }
                    
                    if !message.content.isEmpty {
                        Text(message.content)
                            .font(BBFont.font(BBFont.sm, .bold))
                            .foregroundColor(BBColors.text)
                    }
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(isUser ? BBColors.primary.opacity(0.12) : BBColors.surface)
                .clipShape(isUser ? AnyShape(userShape) : AnyShape(coachShape))
                .overlay(
                    Group {
                        if isUser {
                            userShape
                                .stroke(BBColors.primary.opacity(0.3), lineWidth: 1)
                        } else {
                            coachShape
                                .stroke(BBColors.border, lineWidth: 1)
                        }
                    }
                    .allowsHitTesting(false)
                )
                // Tactile 3D shadow strips under bubbles
                .background(
                    Group {
                        if isUser {
                            userShape
                                .fill(BBColors.primary.opacity(0.2))
                        } else {
                            coachShape
                                .fill(BBColors.borderSubtle)
                        }
                    }
                    .offset(y: 3)
                )
                .padding(.bottom, 4)

                if let suggestions = message.foodLogSuggestions, !suggestions.isEmpty {
                    VStack(alignment: .leading, spacing: 12) {
                        ForEach(suggestions) { item in
                            foodCard(item)
                        }
                    }
                    .padding(.top, 4)
                    .padding(.bottom, 8)
                    .frame(width: 250) // Beautiful constrained width for food card inside thread
                }

                if let error = logError {
                    Text(error)
                        .font(BBFont.font(BBFont.sm, .bold))
                        .foregroundColor(BBColors.danger)
                        .padding(.horizontal, 4)
                        .padding(.bottom, 4)
                }

                Text(formatTime(message.createdAt))
                    .font(.system(size: 10, weight: .bold))
                    .foregroundColor(BBColors.textMuted)
                    .padding(.horizontal, 4)
            }

            if isUser {
                // User Badge Avatar with Shadow
                ZStack {
                    Circle()
                        .fill(BBColors.secondary)
                        .frame(width: 36, height: 36)
                        .overlay(
                            Circle()
                                .stroke(Color(hex: "0284C7"), lineWidth: 1.5)
                        )
                        .background(
                            Circle()
                                .fill(Color(hex: "0284C7"))
                                .offset(y: 2)
                        )
                    Text("👤")
                        .font(BBFont.font(15, .regular))
                }
                .padding(.bottom, 16)
            } else {
                Spacer(minLength: 60)
            }
        }
        .padding(.horizontal)
    }

    private func formatTime(_ dateString: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        if let date = formatter.date(from: dateString) {
            let display = DateFormatter()
            display.timeStyle = .short
            return display.string(from: date)
        }
        let iso = ISO8601DateFormatter()
        if let date = iso.date(from: dateString) {
            let display = DateFormatter()
            display.timeStyle = .short
            return display.string(from: date)
        }
        return dateString
    }

    // MARK: - Food Suggestions Helpers
    
    private func foodCard(_ item: FoodLogSuggestion) -> some View {
        let isLogged = loggedSuggestionIds.contains(item.id)
        let isLogging = loggingSuggestionIds.contains(item.id)
        
        return VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(mealEmoji(item.mealCategory) + " " + item.mealCategory.capitalized)
                    .font(BBFont.font(13, .black))
                    .foregroundColor(BBColors.dynamicColor(light: "1CB0F6", dark: "60A5FA"))
                Spacer()
                Text("\(item.calories) kcal")
                    .font(BBFont.font(BBFont.sm, .black))
                    .foregroundColor(BBColors.accent)
            }
            
            Text(item.foodName)
                .font(BBFont.font(15, .bold))
                .foregroundColor(BBColors.text)
                .lineLimit(2)
                .multilineTextAlignment(.leading)
            
            HStack(spacing: 6) {
                macroTag(label: "P", value: item.protein, color: .purple)
                macroTag(label: "C", value: item.carbs, color: .orange)
                macroTag(label: "F", value: item.fat, color: .red)
            }
            
            Button {
                logFood(item)
            } label: {
                HStack {
                    if isLogging {
                        ProgressView()
                            .progressViewStyle(CircularProgressViewStyle(tint: .white))
                            .scaleEffect(0.8)
                    } else if isLogged {
                        Image(systemName: "checkmark.circle.fill")
                    } else {
                        Image(systemName: "plus")
                    }
                    
                    Text(isLogging ? "Logging..." : (isLogged ? "Logged ✓" : "Add to Log"))
                }
                .frame(maxWidth: .infinity)
            }
            .buttonStyle(BBButtonStyle(
                backgroundColor: isLogged ? BBColors.success : BBColors.primary,
                shadowColor: isLogged ? BBColors.successBorder : BBColors.primaryHover,
                isEnabled: !isLogged && !isLogging
            ))
            .disabled(isLogged || isLogging)
            .padding(.top, 4)
        }
        .bbCard(radius: BBRadius.md, padding: 12)
    }
    
    private func mealEmoji(_ category: String) -> String {
        switch category.lowercased() {
        case "breakfast": return "🥐"
        case "lunch": return "🍱"
        case "dinner": return "🍽️"
        default: return "🍎"
        }
    }
    
    private func macroTag(label: String, value: Double, color: Color) -> some View {
        HStack(spacing: 2) {
            Text(label)
                .font(.system(size: 10, weight: .heavy))
                .foregroundColor(color)
            Text(String(format: "%.1fg", value))
                .font(.system(size: 10, weight: .bold))
                .foregroundColor(BBColors.textSecondary)
        }
        .padding(.vertical, 3)
        .padding(.horizontal, 6)
        .background(BBColors.surfaceAlt)
        .cornerRadius(6)
    }
    
    private func logFood(_ item: FoodLogSuggestion) {
        guard !loggedSuggestionIds.contains(item.id), !loggingSuggestionIds.contains(item.id) else {
            return
        }

        logError = nil
        loggingSuggestionIds.insert(item.id)
        
        let payload = IntakeFormPayload(
            foodItem: item.foodName,
            calories: item.calories,
            protein: item.protein,
            carbs: item.carbs,
            fat: item.fat,
            mealCategory: normalizedMealCategory(item.mealCategory)
        )
        
        Task { @MainActor in
            do {
                _ = try await session.createIntake(payload)

                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.success)
                
                loggingSuggestionIds.remove(item.id)
                loggedSuggestionIds.insert(item.id)
                logError = nil
            } catch {
                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.error)
                
                loggingSuggestionIds.remove(item.id)
                logError = error.localizedDescription
            }
        }
    }

    private func normalizedMealCategory(_ rawValue: String) -> String {
        let value = rawValue.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        switch value {
        case "breakfast", "lunch", "dinner", "snack":
            return value
        default:
            return "snack"
        }
    }
}

// MARK: - AnyShape Type Eraser Helper for SwiftUI 14/15 compatibility
struct AnyShape: Shape {
    private let _path: (CGRect) -> Path

    init<S: Shape>(_ shape: S) {
        self._path = shape.path
    }

    func path(in rect: CGRect) -> Path {
        _path(rect)
    }
}

// MARK: - Typing Indicator

private struct TypingIndicator: View {
    @State private var animating = false

    var body: some View {
        let coachShape = AsymmetricCornerShape(topLeft: 18, topRight: 18, bottomLeft: 4, bottomRight: 18)
        
        HStack(alignment: .bottom, spacing: 10) {
            ZStack {
                Circle()
                    .fill(BBColors.primaryGradient)
                    .frame(width: 36, height: 36)
                    .overlay(
                        Circle()
                            .stroke(BBColors.primaryHover, lineWidth: 1.5)
                    )
                    .background(
                        Circle()
                            .fill(BBColors.primaryHover)
                            .offset(y: 2)
                    )
                Text("✨")
                    .font(BBFont.font(15, .regular))
            }
            .padding(.bottom, 16)

            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 6) {
                    ForEach(0..<3, id: \.self) { index in
                        Circle()
                            .fill(BBColors.textMuted)
                            .frame(width: 8, height: 8)
                            .scaleEffect(animating ? 1.0 : 0.4)
                            .animation(
                                .easeInOut(duration: 0.6)
                                    .repeatForever()
                                    .delay(Double(index) * 0.15),
                                value: animating
                            )
                    }
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(BBColors.surface)
                .clipShape(coachShape)
                .overlay(
                    coachShape
                        .stroke(BBColors.border, lineWidth: 1)
                        .allowsHitTesting(false)
                )
                .background(
                    coachShape
                        .fill(BBColors.borderSubtle)
                        .offset(y: 3)
                )
                .padding(.bottom, 4)
                
                Text("Typing...")
                    .font(.system(size: 10, weight: .bold))
                    .foregroundColor(BBColors.textMuted)
                    .padding(.horizontal, 4)
            }
            Spacer()
        }
        .padding(.horizontal)
        .onAppear { animating = true }
    }
}
