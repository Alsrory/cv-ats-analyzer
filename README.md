# AI-Powered ATS CV Analyzer 🚀

An advanced, enterprise-grade resume analysis tool that leverages **Google Gemini AI** to bridge the gap between job seekers and Applicant Tracking Systems (ATS). This tool simulates the logic used by major platforms like Workday, Greenhouse, and Lever to provide actionable feedback on resume-job description alignment.

## 🌟 Key Features

### 🧠 Intelligent Core
* **Weighted Scoring Engine:** Calculates an overall ATS score based on Semantic Match (40%), Structural Integrity (30%), Technical Readability (20%), and Formatting (10%).
* **Semantic Matching:** Goes beyond simple keyword counting to understand the context and depth of professional experience.
* **ATS "Killer" Detection:** Identifies elements that break automated parsers, such as complex tables, non-standard fonts, and embedded images.

### 🛡️ Robust Backend Architecture
* **API Key Rotation:** Supports multiple API keys and automatically rotates them upon encountering Rate Limit or Quota Exceeded (429) errors.
* **Model Failover System:** Automatically switches between `gemini-2.5-flash` and `gemini-2.0-flash` to ensure high availability.
* **Self-Healing JSON Parsing:** Features advanced regex fallback and control-character sanitization to handle inconsistent LLM responses.
* **Smart Retry Logic:** Built-in handling for Rate Limits (429) and Service Unavailable (503) errors.

### 💻 User Interface
* **Analysis Dashboard:** A clean web interface to upload resumes, paste job descriptions, and view detailed analytical results.

---

## 🛠️ Tech Stack
* **Language:** PHP 8.1+
* **AI Engine:** Google Gemini API (Flash Models)
* **Server:** PHP Built-in Web Server (Dev) / Apache (Production)
* **Containerization:** Docker
* **Architecture:** Object-Oriented (OOP) logic

---

## 🚀 Installation & Quick Start

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/Alsrory/cv-ats-analyzer.git
   cd cv-ats-analyzer
   ```

### ⚙️ Configure Environment
Copy the example environment file to create your `.env` and add your **Gemini API Key(s) 🔑**:

```bash
cp .env.example .env
```

Open `.env` and add your key. To use multiple keys for automatic rotation, separate them with commas:
```env
# Single Key
GEMINI_API_KEY="YOUR_KEY"

# Multiple Keys (for automatic rotation on rate limit)
GEMINI_API_KEYS="KEY1,KEY2,KEY3"
```

### 🚀 Run the Local Server (Development)
Launch the application using the PHP built-in server from the project root:

```bash
php -S localhost:8000
```
Open [http://localhost:8000](http://localhost:8000) in your web browser.

### 🐳 Run with Docker (Recommended)
This project includes a production-ready `Dockerfile` based on Apache and PHP 8.1.

1. **Build the Docker Image:**
   ```bash
   docker build -t cv-ats-analyzer .
   ```

2. **Run the Container:**
   You can pass your API keys directly via environment variables:
   ```bash
   docker run -d -p 8080:80 \
     -e GEMINI_API_KEYS="YOUR_KEY_1,YOUR_KEY_2" \
     --name cv-analyzer \
     cv-ats-analyzer
   ```
   *Or use your existing `.env` file:*
   ```bash
   docker run -d -p 8080:80 \
     --env-file .env \
     --name cv-analyzer \
     cv-ats-analyzer
   ```

3. **Access the Application:**
   Open [http://localhost:8080](http://localhost:8080) in your web browser.



### 📊 Analysis Pipeline
* **Sanitization:** The engine cleans raw text to remove hidden Unicode characters that break JSON structures.
* **Prompt Engineering:** A highly structured 4-tier audit prompt is sent to the Gemini API.
* **Validation:** The response is normalized against a strict JSON schema to ensure data integrity.
* **Visualization:** Data is mapped to the Results UI for the user.





### 📄 License
This project is licensed under the **MIT License**

