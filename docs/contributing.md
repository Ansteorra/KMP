# Contributing Guidelines

This document outlines the guidelines and procedures for contributing to the Kingdom Management Portal (KMP) project.

## Getting Started

### Repository Setup

1. **Fork the repository**
   - Visit [https://github.com/Ansteorra/KMP](https://github.com/Ansteorra/KMP)
   - Click the "Fork" button to create your own copy of the repository

2. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR-USERNAME/KMP.git
   cd KMP
   ```

3. **Set up upstream remote**
   ```bash
   git remote add upstream https://github.com/Ansteorra/KMP.git
   ```

4. **Create a branch for your work**
   ```bash
   git checkout -b feature/my-new-feature
   ```

### Development Environment Setup

Follow the instructions in the [Deployment and Environment Setup](./deployment.md) document to set up your development environment.

## Contribution Workflow

### 1. Find or Create an Issue

Before starting work, make sure there's an issue in the GitHub issue tracker that describes the feature or bug you're working on. If not, create one.

- Use clear, descriptive titles
- Provide detailed information about the feature or bug
- Include steps to reproduce for bugs
- Attach screenshots or mockups if relevant

### 2. Discuss the Approach

For significant changes, discuss your approach in the issue comments before starting work to ensure alignment with project goals and architecture.

### 3. Write Code

Follow these guidelines when writing code:

- Adhere to the [Coding Standards](./coding-standards.md)
- Include appropriate tests for your changes
- Document your code with PHPDoc comments
- Keep changes focused and minimal
- Commit frequently with clear messages

### 4. Create a Pull Request

When your changes are ready for review:

1. Push your branch to your fork:
   ```bash
   git push origin feature/my-new-feature
   ```

2. Go to the original KMP repository on GitHub
3. Click "New Pull Request"
4. Select "compare across forks"
5. Select your fork and branch
6. Fill out the pull request template with:
   - A clear title that summarizes the change
   - A detailed description of the changes
   - Reference to the issue (e.g., "Fixes #123")
   - Any additional context or information

### 5. Code Review

After submitting your pull request:

- Address any feedback from reviewers
- Make additional commits as needed
- Keep the PR updated if the main branch changes
- Be responsive to questions and comments

## Pull Request Guidelines

### PR Size

- Keep PRs focused on a single issue or feature
- Aim for PRs that change fewer than 500 lines of code
- Consider breaking large changes into multiple PRs

### PR Description

Include the following in your PR description:

- What the change does
- Why the change is needed
- How to test the change
- Any migration steps or configuration changes needed
- Screenshots or GIFs for UI changes

### PR Checklist

Before submitting, ensure your PR:

- [ ] Passes all tests
- [ ] Meets coding standards
- [ ] Includes necessary documentation updates
- [ ] Includes appropriate tests
- [ ] References related issues
- [ ] Has been tested locally

## Testing Guidelines

All contributions should include appropriate tests:

- **Unit tests** for individual components
- **Integration tests** for workflows
- **Controller tests** for HTTP endpoints

See the [Testing and Quality Assurance](./testing.md) document for detailed testing guidelines.

## Documentation

Update documentation alongside code changes:

- Update relevant files in the `documentation/` directory
- Include PHPDoc comments in your code
- Update README files in plugins if needed
- Consider adding examples for complex features

## Git Practices

### Commit Messages

Follow this format for commit messages:

```
[Type] Short summary (50 chars max)

More detailed explanation if needed. Wrap at about 72 characters.
Explain what and why, not how (the code shows the how).

Resolves: #123
```

Types include:
- `[Feature]` - New functionality
- `[Fix]` - Bug fixes
- `[Docs]` - Documentation changes
- `[Style]` - Code style changes
- `[Refactor]` - Code changes that neither fix bugs nor add features
- `[Test]` - Adding or correcting tests
- `[Chore]` - Changes to the build process, dependencies, etc.

### Branch Naming

Name your branches according to their purpose:

- `feature/short-description` - For new features
- `bugfix/short-description` - For bug fixes
- `docs/short-description` - For documentation changes
- `refactor/short-description` - For code refactoring

### Keeping Your Fork Updated

Regularly update your fork with changes from the upstream repository:

```bash
git checkout main
git pull upstream main
git push origin main
```

When working on a branch that needs updating:

```bash
git checkout feature/my-feature
git rebase main
git push -f origin feature/my-feature
```

## Code Review Process

### Reviewer Guidelines

If you're reviewing someone else's code:

- Be respectful and constructive
- Focus on the code, not the person
- Consider architecture and design
- Check for bugs and edge cases
- Verify test coverage
- Look for documentation
- Provide specific suggestions

### Author Guidelines

When your code is being reviewed:

- Be open to feedback
- Explain your reasoning for decisions
- Address all comments
- Ask questions if feedback is unclear
- Update your code promptly

## Community Guidelines

### Communication

- Be respectful and inclusive
- Assume good intentions
- Stay focused on the topic
- Use clear, concise language
- Be patient, especially with new contributors

### Issue Discussions

- Keep discussion on-topic
- Provide context and examples
- Link to relevant documentation or code
- Use GitHub features (code blocks, task lists, etc.)
- Be mindful of everyone's time

## CakePHP Specific Guidelines

### Plugin Development

When contributing to or creating plugins:

- Follow CakePHP plugin structure
- Include clear documentation
- Provide migration files for database changes
- Follow CakePHP naming conventions
- Write comprehensive tests

### Extending Core Components

When extending core components:

- Understand the component's purpose and design
- Consider composability and reusability
- Document extension points
- Avoid breaking changes

## Legal Considerations

### License

By contributing to KMP, you agree that your contributions will be licensed under the [MIT License](../LICENSE).

### Copyright

- Do not submit code that you don't have the rights to
- Respect third-party licenses
- Include appropriate attribution for third-party code

## Getting Help

If you need help with your contribution:

- Ask questions in the issue you're working on
- Refer to the [documentation](./README.md)
- Reach out to project maintainers

## Next Steps

- For information about coding standards, see [Coding Standards and Practices](./coding-standards.md)
- To understand testing procedures, see [Testing and Quality Assurance](./testing.md)
- For deployment instructions, see [Deployment and Environment Setup](./deployment.md)