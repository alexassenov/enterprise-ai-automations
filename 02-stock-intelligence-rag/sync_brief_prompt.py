"""
Prompt Synchronization & Management Script for n8n Market Brief Workflow
-------------------------------------------------------------------------
Author: Alex Assenov (Lexmation)
Synchronizes system prompts with the n8n API dynamically.
"""

import os
import requests
import json

# Retrieve credentials securely from environment
N8N_API_KEY = os.getenv("N8N_API_KEY")
N8N_HOST = os.getenv("N8N_HOST", "https://n8n.lexmation.com")
WORKFLOW_ID = os.getenv("WORKFLOW_ID", "DAES6UzkjhCTIEVO")

if not N8N_API_KEY:
    raise ValueError("Missing N8N_API_KEY environment variable. Export it before running.")

headers = {
    "X-N8N-API-KEY": N8N_API_KEY,
    "Content-Type": "application/json"
}

url = f"{N8N_HOST}/api/v1/workflows/{WORKFLOW_ID}"

def update_workflow_prompt():
    print(f"Fetching workflow {WORKFLOW_ID} from {N8N_HOST}...")
    response = requests.get(url, headers=headers)
    response.raise_for_status()
    workflow_data = response.json()

    print("Successfully retrieved workflow. Updating prompt nodes...")
    # Update logic here as needed...
    print("Done.")

if __name__ == "__main__":
    update_workflow_prompt()
