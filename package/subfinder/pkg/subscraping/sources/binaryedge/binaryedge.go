// Package binaryedge logic
package binaryedge

import (
	"context"
	"fmt"
	"math"
	"net/url"
	"strconv"

	jsoniter "github.com/json-iterator/go"
	"github.com/projectdiscovery/subfinder/v2/pkg/subscraping"
)

const (
	v1                = "v1"
	v2                = "v2"
	baseAPIURLFmt     = "https://api.binaryedge.io/%s/query/domains/subdomain/%s"
	v2SubscriptionURL = "https://api.binaryedge.io/v2/user/subscription"
	v1PageSizeParam   = "pagesize"
	pageParam         = "page"
	firstPage         = 1
	maxV1PageSize     = 10000
)

type subdomainsResponse struct {
	Message    string      `json:"message"`
	Title      string      `json:"title"`
	Status     interface{} `json:"status"` // string for v1, int for v2
	Subdomains []string    `json:"events"`
	Page       int         `json:"page"`
	PageSize   int         `json:"pagesize"`
	Total      int         `json:"total"`
}

// Source is the passive scraping agent
type Source struct{}

// Run function returns all subdomains found with the service
func (s *Source) Run(ctx context.Context, domain string, session *subscraping.Session) <-chan subscraping.Result {
	results := make(chan subscraping.Result)

	go func() {
		defer close(results)

		if session.Keys.Binaryedge == "" {
			return
		}

		var baseURL string

		authHeader := map[string]string{"X-Key": session.Keys.Binaryedge}

		if isV2(ctx, session, authHeader) {
			baseURL = fmt.Sprintf(baseAPIURLFmt, v2, domain)
		} else {
			authHeader = map[string]string{"X-Token": session.Keys.Binaryedge}
			v1URLWithPageSize, err := addURLParam(fmt.Sprintf(baseAPIURLFmt, v1, domain), v1PageSizeParam, strconv.Itoa(maxV1PageSize))
			if err != nil {
				results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
				return
			}
			baseURL = v1URLWithPageSize.String()
		}

		if baseURL == "" {
			results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: fmt.Errorf("can't get API URL")}
			return
		}

		s.enumerate(ctx, session, baseURL, firstPage, authHeader, results)
	}()

	return results
}

func (s *Source) enumerate(ctx context.Context, session *subscraping.Session, baseURL string, page int, authHeader map[string]string, results chan subscraping.Result) {
	pageURL, err := addURLParam(baseURL, pageParam, strconv.Itoa(page))
	if err != nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		return
	}

	resp, err := session.Get(ctx, pageURL.String(), "", authHeader)
	if err != nil && resp == nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		session.DiscardHTTPResponse(resp)
		return
	}

	var response subdomainsResponse
	err = jsoniter.NewDecoder(resp.Body).Decode(&response)
	if err != nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: err}
		resp.Body.Close()
		return
	}

	// Check error messages
	if response.Message != "" && response.Status != nil {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Error, Error: fmt.Errorf(response.Message)}
	}

	resp.Body.Close()

	for _, subdomain := range response.Subdomains {
		results <- subscraping.Result{Source: s.Name(), Type: subscraping.Subdomain, Value: subdomain}
	}

	totalPages := int(math.Ceil(float64(response.Total) / float64(response.PageSize)))
	nextPage := response.Page + 1
	for currentPage := nextPage; currentPage <= totalPages; currentPage++ {
		s.enumerate(ctx, session, baseURL, currentPage, authHeader, results)
	}
}

// Name returns the name of the source
func (s *Source) Name() string {
	return "binaryedge"
}

func isV2(ctx context.Context, session *subscraping.Session, authHeader map[string]string) bool {
	resp, err := session.Get(ctx, v2SubscriptionURL, "", authHeader)
	if err != nil {
		session.DiscardHTTPResponse(resp)
		return false
	}

	resp.Body.Close()

	return true
}

func addURLParam(targetURL, name, value string) (*url.URL, error) {
	u, err := url.Parse(targetURL)
	if err != nil {
		return u, err
	}
	q, _ := url.ParseQuery(u.RawQuery)
	q.Add(name, value)
	u.RawQuery = q.Encode()

	return u, nil
}
