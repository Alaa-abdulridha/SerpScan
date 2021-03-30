// Package github GitHub search package
// Based on gwen001's https://github.com/gwen001/github-search github-subdomains
package github

import (
	"bufio"
	"context"
	"fmt"
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"

	jsoniter "github.com/json-iterator/go"

	"github.com/projectdiscovery/gologger"
	"github.com/projectdiscovery/subfinder/v2/pkg/subscraping"
	"github.com/tomnomnom/linkheader"
)

type textMatch struct {
	Fragment string `json:"fragment"`
}

type item struct {
	Name        string      `json:"name"`
	HTMLURL     string      `json:"html_url"`
	TextMatches []textMatch `json:"text_matches"`
}

type response struct {
	TotalCount int    `json:"total_count"`
	Items      []item `json:"items"`
}

// Source is the passive scraping agent
type Source struct{}

// Run function returns all subdomains found with the service
func (s *Source) Run(ctx context.Context, domain string, session *subscraping.Session) <-chan subscraping.Result {
	results := make(chan subscraping.Result)

	go func() {
		defer close(results)

		if len(session.Keys.GitHub) == 0 {
			return
		}

		tokens := NewTokenManager(session.Keys.GitHub)

		searchURL := fmt.Sprintf("https://api.github.com/search/code?per_page=100&q=%s&sort=created&order=asc", domain)
		s.enumerate(ctx, searchURL, domainRegexp(domain), tokens, session, results)
	}()

	return results
}

func (s *Source) enumerate(ctx context.Context, searchURL string, domainRegexp *regexp.Regexp, tokens *Tokens, session *subscraping.Session, results chan subscraping.Result) {
	select {
	case <-ctx.Done():
		return
	default:
	}

	token := tokens.Get()

	if token.RetryAfter > 0 {
		if len(tokens.pool) == 1 {
			gologger.Verbose().Label(s.Name()).Msgf("GitHub Search request rate limit exceeded, waiting for %d seconds before retry... \n", token.RetryAfter)
			time.Sleep(time.Duration(token.RetryAfter) * time.Second)
		} else {
			token = tokens.Get()
		}
	}

	headers := map[string]string{"Accept": "application/vnd.github.v3.text-match+json", "Authorization": "token " + token.Hash}

	// Initial request to GitHub search
	resp, err := session.Get(ctx, searchURL, "", headers)
	isForbidden := resp != nil && resp.StatusCode == http.StatusForbidden
	if err != nil && !isForbidden {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		session.DiscardHTTPResponse(resp)
		return
	}

	// Retry enumerarion after Retry-After seconds on rate limit abuse detected
	ratelimitRemaining, _ := strconv.ParseInt(resp.Header.Get("X-Ratelimit-Remaining"), 10, 64)
	if isForbidden && ratelimitRemaining == 0 {
		retryAfterSeconds, _ := strconv.ParseInt(resp.Header.Get("Retry-After"), 10, 64)
		tokens.setCurrentTokenExceeded(retryAfterSeconds)
		resp.Body.Close()

		s.enumerate(ctx, searchURL, domainRegexp, tokens, session, results)
	}

	var data response

	// Marshall json response
	err = jsoniter.NewDecoder(resp.Body).Decode(&data)
	if err != nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		resp.Body.Close()
		return
	}

	resp.Body.Close()

	err = proccesItems(ctx, data.Items, domainRegexp, s.Name(), session, results)
	if err != nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		return
	}

	// Links header, first, next, last...
	linksHeader := linkheader.Parse(resp.Header.Get("Link"))
	// Process the next link recursively
	for _, link := range linksHeader {
		if link.Rel == "next" {
			nextURL, err := url.QueryUnescape(link.URL)
			if err != nil {
				results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
				return
			}
			s.enumerate(ctx, nextURL, domainRegexp, tokens, session, results)
		}
	}
}

// proccesItems procceses github response items
func proccesItems(ctx context.Context, items []item, domainRegexp *regexp.Regexp, name string, session *subscraping.Session, results chan subscraping.Result) error {
	for _, item := range items {
		// find subdomains in code
		resp, err := session.SimpleGet(ctx, rawURL(item.HTMLURL))
		if err != nil {
			if resp != nil && resp.StatusCode != http.StatusNotFound {
				session.DiscardHTTPResponse(resp)
			}
			return err
		}

		if resp.StatusCode == http.StatusOK {
			scanner := bufio.NewScanner(resp.Body)
			for scanner.Scan() {
				line := scanner.Text()
				if line == "" {
					continue
				}
				for _, subdomain := range domainRegexp.FindAllString(normalizeContent(line), -1) {
					results <- subscraping.Result{Source: name, Type: subscraping.Subdomain, Value: subdomain}
				}
			}
			resp.Body.Close()
		}

		// find subdomains in text matches
		for _, textMatch := range item.TextMatches {
			for _, subdomain := range domainRegexp.FindAllString(normalizeContent(textMatch.Fragment), -1) {
				results <- subscraping.Result{Source: name, Type: subscraping.Subdomain, Value: subdomain}
			}
		}
	}
	return nil
}

// Normalize content before matching, query unescape, remove tabs and new line chars
func normalizeContent(content string) string {
	normalizedContent, _ := url.QueryUnescape(content)
	normalizedContent = strings.ReplaceAll(normalizedContent, "\\t", "")
	normalizedContent = strings.ReplaceAll(normalizedContent, "\\n", "")
	return normalizedContent
}

// Raw URL to get the files code and match for subdomains
func rawURL(htmlURL string) string {
	domain := strings.ReplaceAll(htmlURL, "https://github.com/", "https://raw.githubusercontent.com/")
	return strings.ReplaceAll(domain, "/blob/", "/")
}

// DomainRegexp regular expression to match subdomains in github files code
func domainRegexp(domain string) *regexp.Regexp {
	rdomain := strings.ReplaceAll(domain, ".", "\\.")
	return regexp.MustCompile("(\\w[a-zA-Z0-9][a-zA-Z0-9-\\.]*)" + rdomain)
}

// Name returns the name of the source
func (s *Source) Name() string {
	return "github"
}
