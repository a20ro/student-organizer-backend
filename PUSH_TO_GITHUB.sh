#!/bin/bash
# Script to push to GitHub
# Replace REPO_NAME with your chosen repository name (e.g., student-backend)

REPO_NAME="student-organizer"  # Repository name
GITHUB_USER="a20ro"

echo "🚀 Pushing to GitHub..."
echo "Repository: https://github.com/$GITHUB_USER/$REPO_NAME"
echo ""

# Add remote (if not already added)
git remote remove origin 2>/dev/null
git remote add origin https://github.com/$GITHUB_USER/$REPO_NAME.git

# Set branch to main
git branch -M main

# Push to GitHub
echo "Pushing to GitHub..."
git push -u origin main

echo ""
echo "✅ Done! Your code is now on GitHub:"
echo "   https://github.com/$GITHUB_USER/$REPO_NAME"
