#!/usr/bin/env bash
set -euo pipefail

files=(README.md terraform/README.md)
while IFS= read -r file; do
    files+=("${file}")
done < <(find docs -type f -name '*.md' -print | sort)

required=(
    docs/index.md
    docs/11-operations/service-catalogue.md
    docs/11-operations/endpoint-catalogue.md
    docs/11-operations/exception-catalogue.md
    docs/11-operations/telemetry-data-dictionary.md
    docs/11-operations/slo-alert-dashboard-catalogue.md
    docs/11-operations/failure-mode-matrix.md
    docs/14-governance/ownership-and-change-control.md
    docs/audits/master-prompt-compliance.md
    docs/audits/prompt-conflict-report.md
    docs/audits/documentation-debt.md
    docs/audits/documentation-validation-report.md
    docs/tenant-master/README.md
    docs/tenant-master/architecture.md
    docs/tenant-master/workbook-migration.md
    docs/tenant-master/data-model.md
    docs/tenant-master/academic-model.md
    docs/tenant-master/roles-capabilities.md
    docs/tenant-master/synchronization.md
    docs/tenant-master/import-packages.md
    docs/tenant-master/operations.md
    docs/tenant-master/developer.md
    docs/tenant-master/testing-acceptance.md
    mkdocs.yml
)
for path in "${required[@]}"; do
    test -s "${path}" || {
        echo "Missing canonical documentation: ${path}" >&2
        exit 1
    }
done

ruby - "${files[@]}" <<'RUBY'
require "pathname"

failures = []

ARGV.each do |file|
  path = Pathname(file)
  next unless path.file?

  content = path.read
  content.scan(/!?\[[^\]]*\]\(([^)]+)\)/).flatten.each do |raw_target|
    target = raw_target.strip.split(/\s+/, 2).first.to_s.delete_prefix("<").delete_suffix(">")
    next if target.empty?
    next if target.start_with?("#", "http://", "https://", "mailto:", "tel:", "//")

    target_path = target.split("#", 2).first
    next if target_path.empty?

    resolved = (path.dirname + target_path).cleanpath
    next if resolved.exist?

    failures << "#{file}: missing local link #{target}"
  end
end

if failures.any?
  warn failures.join("\n")
  exit 1
end

puts "Validated #{ARGV.length} Markdown files."
RUBY

ruby - "${files[@]}" <<'RUBY'
ARGV.each do |file|
  content = File.read(file)
  next if content.scan(/^```mermaid\s*$/).empty?

  fences = content.scan(/^```\s*$/).length
  mermaid = content.scan(/^```mermaid\s*$/).length
  abort("#{file}: unbalanced Mermaid code fence") if fences < mermaid
end
puts "Validated Mermaid fence contracts."
RUBY

ruby -rjson -e 'JSON.parse(File.read(ARGV.fetch(0)))' docs/audits/documentation-inventory.json
